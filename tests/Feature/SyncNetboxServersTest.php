<?php

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Jobs\SyncNetboxServers;
use App\Models\ActivityLog;
use App\Models\Server;
use App\Models\Team;
use App\Services\Netbox\NetboxClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Fake NetBox's active devices and VMs endpoints, then run the sync against them.
 *
 * @param  array<int, array<string, mixed>>  $devices
 * @param  array<int, array<string, mixed>>  $vms
 */
function runNetboxSync(array $devices = [], array $vms = []): void
{
    Http::fake([
        'netbox.test/api/dcim/devices/*' => Http::response(['results' => $devices, 'next' => null]),
        'netbox.test/api/virtualization/virtual-machines/*' => Http::response(['results' => $vms, 'next' => null]),
    ]);

    (new SyncNetboxServers)->handle(new NetboxClient('https://netbox.test', 'key', 'token'));
}

/**
 * A single NetBox object as the API would return it.
 *
 * @return array<string, mixed>
 */
function netboxRecord(int $id, string $name, ?string $platform = null): array
{
    return ['id' => $id, 'name' => $name, 'platform' => $platform === null ? null : ['name' => $platform]];
}

it('creates a new netbox device in triage with the configured default cadence', function () {
    config([
        'patchmon.triage_defaults.interval_months' => 3,
        'patchmon.triage_defaults.grace_value' => 2,
        'patchmon.triage_defaults.grace_units' => 'weeks',
    ]);

    runNetboxSync(devices: [netboxRecord(5, 'WEB01.example.com', 'Ubuntu 22.04')]);

    $server = Server::where('netbox_id', 5)->where('is_virtual', false)->first();

    expect($server)->not->toBeNull()
        ->and($server->team_id)->toBeNull()
        ->and($server->created_by_user_id)->toBeNull()
        ->and($server->name)->toBe('web01.example.com')
        ->and($server->os_type)->toBe(OsType::Linux)
        ->and($server->is_virtual)->toBeFalse()
        ->and($server->interval_months)->toBe(3)
        ->and($server->grace_value)->toBe(2)
        ->and($server->grace_units)->toBe(GraceUnit::Weeks);
});

it('logs an automated activity row when netbox discovers a new server', function () {
    runNetboxSync(devices: [netboxRecord(5, 'web01.example.com', 'Ubuntu 22.04')]);

    $server = Server::where('netbox_id', 5)->first();
    $log = ActivityLog::sole();

    expect($log->user_id)->toBeNull();
    expect($log->actorLabel())->toBe('Automated');
    expect($log->server_id)->toBe($server->id);
    expect($log->description)->toContain('NetBox');
});

it('logs an automated activity row when a server drops out of netbox', function () {
    $gone = Server::factory()->fromNetbox(5)->create(['name' => 'gone.example.com']);

    runNetboxSync(devices: [netboxRecord(6, 'still-here.example.com', 'Ubuntu 22.04')]);

    $log = ActivityLog::where('server_id', $gone->id)->sole();
    expect($log->user_id)->toBeNull();
    expect($log->description)->toContain('inactive');
});

it('does not log when a netbox sync changes nothing on an existing server', function () {
    Server::factory()->fromNetbox(5, false)->create([
        'name' => 'web01.example.com',
        'os_type' => OsType::Linux,
    ]);

    runNetboxSync(devices: [netboxRecord(5, 'web01.example.com', 'Ubuntu 22.04')]);

    expect(ActivityLog::count())->toBe(0);
});

it('creates a new netbox virtual machine flagged as virtual', function () {
    runNetboxSync(vms: [netboxRecord(5, 'vm-app01.example.com', 'Debian 12')]);

    $server = Server::where('netbox_id', 5)->where('is_virtual', true)->first();

    expect($server)->not->toBeNull()
        ->and($server->is_virtual)->toBeTrue()
        ->and($server->name)->toBe('vm-app01.example.com')
        ->and($server->os_type)->toBe(OsType::Linux);
});

it('refreshes name and os on an existing synced server without touching team or cadence', function () {
    $team = Team::factory()->create();
    $server = Server::factory()
        ->forTeam($team)
        ->fromNetbox(5)
        ->withInterval(6)
        ->create([
            'name' => 'old-name.example.com',
            'os_type' => OsType::Other,
        ]);

    runNetboxSync(devices: [netboxRecord(5, 'NEW-NAME.example.com', 'Windows Server 2022')]);

    $server->refresh();

    expect($server->name)->toBe('new-name.example.com')
        ->and($server->os_type)->toBe(OsType::Windows)
        ->and($server->team_id)->toBe($team->id)
        ->and($server->interval_months)->toBe(6)
        ->and($server->created_by_user_id)->not->toBeNull();

    expect(Server::count())->toBe(1);
});

it('skips updating an existing synced server when netbox sends an invalid hostname', function () {
    $server = Server::factory()->fromNetbox(5)->create([
        'name' => 'old-name.example.com',
        'os_type' => OsType::Linux,
    ]);

    runNetboxSync(devices: [netboxRecord(5, 'not a valid hostname', 'Windows Server 2022')]);

    $server->refresh();
    $summary = Cache::get('netbox.last_sync_summary');

    expect($server->name)->toBe('old-name.example.com')
        ->and($server->os_type)->toBe(OsType::Linux)
        ->and(Server::count())->toBe(1)
        ->and($summary['invalid'])->toBe(['not a valid hostname'])
        ->and($summary['updated'])->toBe(0);
});

it('skips updating an existing synced server when the new netbox name collides with another server', function () {
    $manual = Server::factory()->create([
        'name' => 'shared.example.com',
        'os_type' => OsType::Linux,
    ]);
    $synced = Server::factory()->fromNetbox(7)->create([
        'name' => 'old-name.example.com',
        'os_type' => OsType::Other,
    ]);

    runNetboxSync(devices: [netboxRecord(7, 'SHARED.example.com', 'Windows Server 2022')]);

    $manual->refresh();
    $synced->refresh();
    $summary = Cache::get('netbox.last_sync_summary');

    expect($manual->netbox_id)->toBeNull()
        ->and($manual->name)->toBe('shared.example.com')
        ->and($synced->name)->toBe('old-name.example.com')
        ->and($synced->os_type)->toBe(OsType::Other)
        ->and(Server::count())->toBe(2)
        ->and($summary['conflicts'])->toBe(['SHARED.example.com'])
        ->and($summary['updated'])->toBe(0);
});

it('skips a netbox object whose name collides with a manual server', function () {
    $manual = Server::factory()->create([
        'name' => 'shared.example.com',
        'os_type' => OsType::Linux,
    ]);

    runNetboxSync(devices: [netboxRecord(7, 'SHARED.example.com', 'Windows Server 2022')]);

    $manual->refresh();

    expect($manual->netbox_id)->toBeNull()
        ->and($manual->os_type)->toBe(OsType::Linux)
        ->and(Server::where('netbox_id', 7)->exists())->toBeFalse()
        ->and(Server::count())->toBe(1);

    expect(Cache::get('netbox.last_sync_summary')['conflicts'])->toBe(['SHARED.example.com']);
});

it('skips and reports a netbox server whose name is not a valid hostname', function () {
    runNetboxSync(devices: [
        netboxRecord(5, 'good.example.com', 'Ubuntu 22.04'),
        netboxRecord(6, 'jim.compsci (matlab server)', 'Ubuntu 22.04'),
    ]);

    expect(Server::where('netbox_id', 5)->exists())->toBeTrue()
        ->and(Server::where('netbox_id', 6)->exists())->toBeFalse()
        ->and(Cache::get('netbox.last_sync_summary')['invalid'])->toBe(['jim.compsci (matlab server)'])
        ->and(Cache::get('netbox.last_sync_summary')['created'])->toBe(1);
});

it('keeps the first server and reports the second when a netbox device and VM share a name', function () {
    runNetboxSync(
        devices: [netboxRecord(5, 'blitzen.physics', 'Ubuntu 22.04')],
        vms: [netboxRecord(7, 'blitzen.physics', 'Debian 12')],
    );

    // devices are fetched before VMs, so the device is kept and the VM is reported
    $kept = Server::where('name', 'blitzen.physics')->get();

    expect($kept)->toHaveCount(1)
        ->and($kept->first()->is_virtual)->toBeFalse()
        ->and($kept->first()->netbox_id)->toBe(5)
        ->and(Cache::get('netbox.last_sync_summary')['conflicts'])->toBe(['blitzen.physics']);
});

it('flags a synced server that has dropped out of the active set and clears its alerting', function () {
    $gone = Server::factory()->fromNetbox(5)->alerting()->create();

    runNetboxSync(devices: [netboxRecord(6, 'still-here.example.com', 'Ubuntu 22.04')]);

    $gone->refresh();

    expect($gone->inactive_since)->not->toBeNull()
        ->and($gone->alerting_since)->toBeNull()
        ->and($gone->last_alerted_at)->toBeNull();
});

it('reactivates a previously inactive server that reappears in the active set', function () {
    $returned = Server::factory()->fromNetbox(5)->inactive()->create();

    runNetboxSync(devices: [netboxRecord(5, 'back-again.example.com', 'Ubuntu 22.04')]);

    $returned->refresh();

    expect($returned->inactive_since)->toBeNull();

    expect(Cache::get('netbox.last_sync_summary')['reactivated'])->toBe(1);
});

it('never flags a manual server as inactive', function () {
    $manual = Server::factory()->create(['name' => 'hand-added.example.com']);

    runNetboxSync(devices: [netboxRecord(6, 'from-netbox.example.com', 'Ubuntu 22.04')]);

    expect($manual->refresh()->inactive_since)->toBeNull();
});

it('keeps device and vm with the same netbox id as separate servers', function () {
    $device = Server::factory()->fromNetbox(5, false)->create(['name' => 'device-5.example.com']);
    $vm = Server::factory()->fromNetbox(5, true)->create(['name' => 'vm-5.example.com']);

    runNetboxSync(
        devices: [netboxRecord(5, 'device-5-renamed.example.com', 'Ubuntu 22.04')],
        vms: [netboxRecord(5, 'vm-5-renamed.example.com', 'Debian 12')],
    );

    expect($device->refresh()->name)->toBe('device-5-renamed.example.com')
        ->and($device->is_virtual)->toBeFalse()
        ->and($vm->refresh()->name)->toBe('vm-5-renamed.example.com')
        ->and($vm->is_virtual)->toBeTrue()
        ->and(Server::count())->toBe(2);
});

it('is idempotent when run twice with the same data', function () {
    runNetboxSync(devices: [netboxRecord(5, 'web01.example.com', 'Ubuntu 22.04')]);

    $server = Server::where('netbox_id', 5)->first();
    $firstUpdatedAt = $server->updated_at;

    $this->travel(1)->hours();

    runNetboxSync(devices: [netboxRecord(5, 'web01.example.com', 'Ubuntu 22.04')]);

    expect(Server::count())->toBe(1)
        ->and($server->refresh()->updated_at->equalTo($firstUpdatedAt))->toBeTrue()
        ->and(Cache::get('netbox.last_sync_summary')['created'])->toBe(0);
});

// The inactive sweep flags every synced server NOT seen this run. If NetBox has a
// transient blip and returns a tiny fraction of the estate, we'd mass-flag the rest
// inactive and clear their alerting — silently suppressing alerts on live servers.
// The guard skips the sweep when the fetched active count falls below
// patchmon.netbox.change_ratio of what we currently track. See ait patchmon-kOKT9.8.
it('does not flag servers inactive when netbox returns an implausibly small set', function () {
    Server::factory()->count(12)->sequence(
        fn ($sequence) => ['netbox_id' => 100 + $sequence->index, 'name' => "synced-{$sequence->index}.example.com"],
    )->create();

    // The API returns just one of the twelve — far too few to be believable.
    runNetboxSync(devices: [netboxRecord(100, 'synced-0.example.com', 'Ubuntu 22.04')]);

    expect(Server::whereNotNull('inactive_since')->count())->toBe(0);
});

it('still applies creates and updates and reports the skip when the sweep is guarded', function () {
    Server::factory()->count(12)->sequence(
        fn ($sequence) => ['netbox_id' => 100 + $sequence->index, 'name' => "synced-{$sequence->index}.example.com"],
    )->create();

    // Too few to trust for the sweep, but the create and update must still happen.
    runNetboxSync(devices: [
        netboxRecord(100, 'synced-0-renamed.example.com', 'Ubuntu 22.04'),
        netboxRecord(200, 'brand-new.example.com', 'Debian 12'),
    ]);

    $summary = Cache::get('netbox.last_sync_summary');

    expect(Server::whereNotNull('inactive_since')->count())->toBe(0)
        ->and($summary['inactive_sweep_skipped'])->toBeTrue()
        ->and(Server::where('netbox_id', 200)->exists())->toBeTrue()
        ->and(Server::where('netbox_id', 100)->first()->name)->toBe('synced-0-renamed.example.com');
});
