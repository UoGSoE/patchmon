<?php

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Jobs\SyncNetboxServers;
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

    (new SyncNetboxServers)->handle(new NetboxClient('https://netbox.test', 'token'));
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
        'patchmon.netbox.default_interval_months' => 3,
        'patchmon.netbox.default_grace_value' => 2,
        'patchmon.netbox.default_grace_units' => 'weeks',
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
