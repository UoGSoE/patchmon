<?php

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Livewire\HomePage;
use App\Models\ActivityLog;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('offers the helper script downloads from the home page', function () {
    $alice = User::factory()->create();

    Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->assertSee(route('scripts.record-patch'))
        ->assertSee(route('scripts.record-patch-ps'));
});

it('shows every server on the All servers tab regardless of team membership', function () {
    $alice = User::factory()->create();
    $myTeam = Team::factory()->create();
    $strangerTeam = Team::factory()->create();
    $alice->teams()->attach($myTeam);

    Server::factory()->forTeam($myTeam)->create(['name' => 'my-team-server.example.test']);
    Server::factory()->forTeam($strangerTeam)->create(['name' => 'other-team-server.example.test']);

    $component = Livewire::actingAs($alice)->test(HomePage::class);

    expect($component->instance()->allServers->pluck('name')->all())
        ->toContain('my-team-server.example.test')
        ->toContain('other-team-server.example.test');
});

it('renders unassigned servers without error and labels the empty team', function () {
    $admin = User::factory()->admin()->create();

    Server::factory()->unassigned()->create(['name' => 'triage-box.example.test']);

    Livewire::actingAs($admin)
        ->test(HomePage::class)
        ->assertSee('triage-box.example.test')
        ->assertSee('Unassigned');
});

it('lists only unassigned servers on the Unassigned tab', function () {
    $admin = User::factory()->admin()->create();
    $team = Team::factory()->create();

    Server::factory()->unassigned()->create(['name' => 'triage-box.example.test']);
    Server::factory()->forTeam($team)->create(['name' => 'owned-box.example.test']);

    $component = Livewire::actingAs($admin)->test(HomePage::class);

    expect($component->instance()->unassignedServers->pluck('name')->all())
        ->toContain('triage-box.example.test')
        ->not->toContain('owned-box.example.test');
});

it('lists never-checked-in servers across every team to any staff user, excluding inactive and patched', function () {
    $user = User::factory()->create();
    $myTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user->teams()->attach($myTeam);

    // Never patched, another team — included (this tab is deliberately not team-scoped).
    Server::factory()->forTeam($otherTeam)->create(['name' => 'other-never.example.test', 'last_patched_at' => null]);
    // Never patched, in triage (no team) — included.
    Server::factory()->unassigned()->create(['name' => 'triage-never.example.test', 'last_patched_at' => null]);
    // Never patched but decommissioned — excluded.
    Server::factory()->forTeam($myTeam)->inactive()->create(['name' => 'inactive-never.example.test', 'last_patched_at' => null]);
    // Has checked in — excluded.
    Server::factory()->forTeam($myTeam)->create(['name' => 'patched.example.test', 'last_patched_at' => now()->subDays(3)]);

    $component = Livewire::actingAs($user)->test(HomePage::class);

    expect($component->instance()->neverCheckedInServers->pluck('name')->all())
        ->toContain('other-never.example.test')
        ->toContain('triage-never.example.test')
        ->not->toContain('inactive-never.example.test')
        ->not->toContain('patched.example.test');
});

it('shows the Unassigned tab', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(HomePage::class)->assertSee('Unassigned servers');
});

it('shows the Never checked in tab and lists never-patched servers to any user', function () {
    $user = User::factory()->create();

    Server::factory()->unassigned()->create(['name' => 'triage-never.example.test', 'last_patched_at' => null]);

    Livewire::actingAs($user)
        ->test(HomePage::class)
        ->assertSee('Never checked in')
        ->assertSee('triage-never.example.test');
});

it('flags inactive servers in the listing', function () {
    $admin = User::factory()->admin()->create();
    $team = Team::factory()->create();

    Server::factory()->forTeam($team)->inactive()->create(['name' => 'decommissioned-box.example.test']);

    Livewire::actingAs($admin)
        ->test(HomePage::class)
        ->assertSee('decommissioned-box.example.test')
        ->assertSee('Inactive');
});

it('bulk-allocates selected unassigned servers to a team and cadence', function () {
    $staff = User::factory()->staff()->create();
    $team = Team::factory()->create();

    $a = Server::factory()->unassigned()->create(['name' => 'triage-a.example.test']);
    $b = Server::factory()->unassigned()->create(['name' => 'triage-b.example.test']);
    $untouched = Server::factory()->unassigned()->create(['name' => 'triage-c.example.test']);

    Livewire::actingAs($staff)
        ->test(HomePage::class)
        ->set('selected', [$a->id, $b->id])
        ->set('allocateTeamId', $team->id)
        ->set('allocateIntervalMonths', 3)
        ->set('allocateGraceValue', 2)
        ->set('allocateGraceUnits', GraceUnit::Weeks->value)
        ->call('bulkAllocate')
        ->assertHasNoErrors();

    $freshA = $a->fresh();
    expect($freshA->team_id)->toBe($team->id)
        ->and($freshA->interval_months)->toBe(3)
        ->and($freshA->grace_value)->toBe(2)
        ->and($freshA->grace_units)->toBe(GraceUnit::Weeks)
        ->and($freshA->created_by_user_id)->toBe($staff->id);

    expect($b->fresh()->team_id)->toBe($team->id);
    expect($untouched->fresh()->team_id)->toBeNull()
        ->and($untouched->fresh()->created_by_user_id)->toBeNull();
});

it('logs an activity row per server when bulk-allocating', function () {
    $staff = User::factory()->staff()->create();
    $team = Team::factory()->create(['name' => 'Networks']);
    $a = Server::factory()->unassigned()->create();
    $b = Server::factory()->unassigned()->create();

    Livewire::actingAs($staff)
        ->test(HomePage::class)
        ->set('selected', [$a->id, $b->id])
        ->set('allocateTeamId', $team->id)
        ->set('allocateGraceUnits', GraceUnit::Days->value)
        ->call('bulkAllocate')
        ->assertHasNoErrors();

    $logs = ActivityLog::all();
    expect($logs)->toHaveCount(2);
    expect($logs->pluck('server_id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
    $logs->each(function ($log) use ($staff) {
        expect($log->user_id)->toBe($staff->id);
        expect($log->description)->toContain('Networks');
        expect($log->description)->toContain('Allocated');
    });
});

it('does not overwrite created_by on an allocated server that already has one', function () {
    $staff = User::factory()->staff()->create();
    $originalCreator = User::factory()->create();
    $team = Team::factory()->create();

    $server = Server::factory()->unassigned()->create([
        'name' => 'already-claimed.example.test',
        'created_by_user_id' => $originalCreator->id,
    ]);

    Livewire::actingAs($staff)
        ->test(HomePage::class)
        ->set('selected', [$server->id])
        ->set('allocateTeamId', $team->id)
        ->set('allocateGraceUnits', GraceUnit::Days->value)
        ->call('bulkAllocate')
        ->assertHasNoErrors();

    $fresh = $server->fresh();
    expect($fresh->team_id)->toBe($team->id)
        ->and($fresh->created_by_user_id)->toBe($originalCreator->id);
});

it('ticking select-all fills the selection with every matching unassigned server', function () {
    $staff = User::factory()->staff()->create();
    Server::factory()->count(3)->unassigned()->create();
    Server::factory()->forTeam(Team::factory()->create())->create(['name' => 'owned.example.test']);

    $component = Livewire::actingAs($staff)
        ->test(HomePage::class)
        ->set('selectAllMatching', true);

    expect($component->get('selected'))->toHaveCount(3);
});

it('clears the triage selection when a filter changes', function () {
    $staff = User::factory()->staff()->create();
    Server::factory()->count(3)->unassigned()->create();

    $component = Livewire::actingAs($staff)
        ->test(HomePage::class)
        ->set('selectAllMatching', true);

    expect($component->get('selected'))->toHaveCount(3);

    $component->set('osFilter', 'windows');

    expect($component->get('selected'))->toBeEmpty()
        ->and($component->get('selectAllMatching'))->toBeFalse();
});

it('allocates every matching unassigned server when select-all-matching is set', function () {
    $staff = User::factory()->staff()->create();
    $team = Team::factory()->create();
    $strangerTeam = Team::factory()->create();

    Server::factory()->count(3)->unassigned()->create();
    $owned = Server::factory()->forTeam($strangerTeam)->create(['name' => 'owned.example.test']);

    Livewire::actingAs($staff)
        ->test(HomePage::class)
        ->set('selectAllMatching', true)
        ->set('allocateTeamId', $team->id)
        ->set('allocateGraceUnits', GraceUnit::Days->value)
        ->call('bulkAllocate')
        ->assertHasNoErrors();

    expect(Server::whereNull('team_id')->count())->toBe(0);
    expect(Server::where('team_id', $team->id)->count())->toBe(3);
    expect($owned->fresh()->team_id)->toBe($strangerTeam->id);
});

it('limits select-all-matching to servers passing the active filter', function () {
    $staff = User::factory()->staff()->create();
    $team = Team::factory()->create();

    $linux = Server::factory()->unassigned()->create(['name' => 'u-linux.example.test', 'os_type' => OsType::Linux]);
    $windows = Server::factory()->unassigned()->create(['name' => 'u-windows.example.test', 'os_type' => OsType::Windows]);

    Livewire::actingAs($staff)
        ->test(HomePage::class)
        ->set('osFilter', 'linux')
        ->set('selectAllMatching', true)
        ->set('allocateTeamId', $team->id)
        ->set('allocateGraceUnits', GraceUnit::Days->value)
        ->call('bulkAllocate')
        ->assertHasNoErrors();

    expect($linux->fresh()->team_id)->toBe($team->id);
    expect($windows->fresh()->team_id)->toBeNull();
});

it('rejects bulk-allocate with no team or cadence and changes nothing', function () {
    $staff = User::factory()->staff()->create();
    $server = Server::factory()->unassigned()->create(['name' => 'triage.example.test']);

    Livewire::actingAs($staff)
        ->test(HomePage::class)
        ->set('selected', [$server->id])
        ->set('allocateTeamId', null)
        ->set('allocateGraceUnits', '')
        ->call('bulkAllocate')
        ->assertHasErrors(['allocateTeamId', 'allocateGraceUnits']);

    expect($server->fresh()->team_id)->toBeNull();
});

it('opens the allocate modal with fresh cadence defaults', function () {
    $staff = User::factory()->staff()->create();

    Livewire::actingAs($staff)
        ->test(HomePage::class)
        ->set('allocateTeamId', 999)
        ->set('allocateGraceUnits', '')
        ->call('openAllocate')
        ->assertSet('allocateTeamId', null)
        ->assertSet('allocateIntervalMonths', 1)
        ->assertSet('allocateGraceValue', 7)
        ->assertSet('allocateGraceUnits', GraceUnit::Days->value);
});

it('applies OS, team and silenced filters to the All servers tab', function () {
    $alice = User::factory()->create();
    $myTeam = Team::factory()->create();
    $strangerTeam = Team::factory()->create();
    $alice->teams()->attach($myTeam);

    Server::factory()->forTeam($myTeam)->create(['name' => 'my-linux-box.example.test', 'os_type' => OsType::Linux]);
    Server::factory()->forTeam($strangerTeam)->create(['name' => 'other-windows-box.example.test', 'os_type' => OsType::Windows]);
    Server::factory()->forTeam($strangerTeam)->create(['name' => 'other-linux-box.example.test', 'os_type' => OsType::Linux]);

    $component = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('osFilter', 'linux');

    expect($component->instance()->allServers->pluck('name')->all())
        ->toContain('my-linux-box.example.test')
        ->toContain('other-linux-box.example.test')
        ->not->toContain('other-windows-box.example.test');
});

it('keeps the Team servers listing scoped to teams the user belongs to', function () {
    $alice = User::factory()->create();
    $netservices = Team::factory()->create();
    $storage = Team::factory()->create();
    $alice->teams()->attach($netservices);

    Server::factory()->forTeam($netservices)->create(['name' => 'network-dns-export.example.test']);
    Server::factory()->forTeam($storage)->create(['name' => 'storage-tape-rotation.example.test']);

    $component = Livewire::actingAs($alice)->test(HomePage::class);

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('network-dns-export.example.test')
        ->not->toContain('storage-tape-rotation.example.test');
});

it('surfaces alerting servers above healthy ones, then sorts the healthy lot alphabetically', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'zebra-healthy.example.test']);
    Server::factory()->forTeam($team)->create(['name' => 'apple-healthy.example.test']);
    Server::factory()->forTeam($team)->create([
        'name' => 'recently-alerting.example.test',
        'alerting_since' => now()->subMinutes(10),
    ]);
    Server::factory()->forTeam($team)->create([
        'name' => 'long-alerting.example.test',
        'alerting_since' => now()->subHours(8),
    ]);

    $component = Livewire::actingAs($alice)->test(HomePage::class);
    $orderedNames = $component->instance()->teamServers->pluck('name')->all();

    expect($orderedNames)->toBe([
        'recently-alerting.example.test',
        'long-alerting.example.test',
        'apple-healthy.example.test',
        'zebra-healthy.example.test',
    ]);
});

it('lists servers from every team the user is a member of on the Team servers tab', function () {
    $alice = User::factory()->create();
    $netservices = Team::factory()->create();
    $storage = Team::factory()->create();
    $alice->teams()->attach([$netservices->id, $storage->id]);

    Server::factory()->forTeam($netservices)->create(['name' => 'network-dns-export.example.test']);
    Server::factory()->forTeam($storage)->create(['name' => 'storage-tape-rotation.example.test']);

    $component = Livewire::actingAs($alice)->test(HomePage::class);

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('network-dns-export.example.test')
        ->toContain('storage-tape-rotation.example.test');
});

it('shows the right empty state when the user has no teams', function () {
    $alice = User::factory()->create();

    Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->assertSee('You are not a member of any teams.');
});

it('redirects unauthenticated visitors away from the home page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('shows alerting servers from the users teams on the Alerting tab', function () {
    $alice = User::factory()->create();
    $aliceTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $alice->teams()->attach($aliceTeam);

    Server::factory()->forTeam($aliceTeam)->alerting()->create(['name' => 'alice-team-alerting.example.test']);
    Server::factory()->forTeam($aliceTeam)->create(['name' => 'alice-team-healthy.example.test']);
    Server::factory()->forTeam($otherTeam)->alerting()->create(['name' => 'other-team-alerting.example.test']);

    $component = Livewire::actingAs($alice)->test(HomePage::class);
    $alertingNames = $component->instance()->alertingServers->pluck('name')->all();

    expect($alertingNames)->toContain('alice-team-alerting.example.test')
        ->not->toContain('alice-team-healthy.example.test')
        ->not->toContain('other-team-alerting.example.test');
});

it('shows every alerting server across the system to admins on the Alerting tab', function () {
    $admin = User::factory()->admin()->create();
    $unrelatedTeam = Team::factory()->create();

    Server::factory()->forTeam($unrelatedTeam)->alerting()->create(['name' => 'stranger-team-alerting.example.test']);
    Server::factory()->forTeam($unrelatedTeam)->create(['name' => 'stranger-team-healthy.example.test']);

    $component = Livewire::actingAs($admin)->test(HomePage::class);
    $alertingNames = $component->instance()->alertingServers->pluck('name')->all();

    expect($alertingNames)->toContain('stranger-team-alerting.example.test')
        ->not->toContain('stranger-team-healthy.example.test');
});

it('filters servers by a fragment of the name on every tab', function () {
    $alice = User::factory()->admin()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->alerting()->create(['name' => 'team-linux-alerting.example.test']);
    Server::factory()->forTeam($team)->create(['name' => 'team-linux-healthy.example.test']);
    Server::factory()->forTeam($team)->create(['name' => 'team-windows-healthy.example.test']);

    $component = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'linux');

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('team-linux-alerting.example.test')
        ->toContain('team-linux-healthy.example.test')
        ->not->toContain('team-windows-healthy.example.test');
    expect($component->instance()->alertingServers->pluck('name')->all())
        ->toContain('team-linux-alerting.example.test');
});

it('also matches the filter against the description', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create([
        'name' => 'nightly-server.example.test',
        'description' => 'rsnapshot of the linux fleet',
    ]);
    Server::factory()->forTeam($team)->create([
        'name' => 'other-server.example.test',
        'description' => 'curl against the order API',
    ]);

    $component = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'linux');

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('nightly-server.example.test')
        ->not->toContain('other-server.example.test');
});

it('ignores filter strings that are blank or only one character', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'alpha-server.example.test']);
    Server::factory()->forTeam($team)->create(['name' => 'beta-server.example.test']);

    $blank = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', '   ');
    expect($blank->instance()->teamServers->pluck('name')->all())
        ->toContain('alpha-server.example.test')
        ->toContain('beta-server.example.test');

    $singleChar = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'a');
    expect($singleChar->instance()->teamServers->pluck('name')->all())
        ->toContain('alpha-server.example.test')
        ->toContain('beta-server.example.test');
});

it('matches the filter against the location column', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'site-server.example.test', 'location' => 'Rankine']);
    Server::factory()->forTeam($team)->create(['name' => 'other-site-server.example.test', 'location' => 'JWS']);
    Server::factory()->forTeam($team)->create(['name' => 'no-location-server.example.test', 'location' => null]);

    $component = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'rankine');

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('site-server.example.test')
        ->not->toContain('other-site-server.example.test')
        ->not->toContain('no-location-server.example.test');
});

it('requires every whitespace-separated token to match', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'linux-backup.example.test', 'location' => 'Rankine']);
    Server::factory()->forTeam($team)->create(['name' => 'linux-mirror.example.test', 'location' => 'JWS']);
    Server::factory()->forTeam($team)->create(['name' => 'windows-backup.example.test', 'location' => 'Rankine']);
    Server::factory()->forTeam($team)->create(['name' => 'unrelated.example.test', 'location' => 'MDR']);

    $component = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('filter', 'linux rankine');

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('linux-backup.example.test')
        ->not->toContain('linux-mirror.example.test')
        ->not->toContain('windows-backup.example.test')
        ->not->toContain('unrelated.example.test');
});

it('narrows the listing by silenced state', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->silenced()->create(['name' => 'quiet-box.example.test']);
    Server::factory()->forTeam($team)->create(['name' => 'noisy-box.example.test']);

    $onlySilenced = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('silencedFilter', 'silenced');

    expect($onlySilenced->instance()->teamServers->pluck('name')->all())
        ->toContain('quiet-box.example.test')
        ->not->toContain('noisy-box.example.test');

    $onlyActive = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('silencedFilter', 'active');

    expect($onlyActive->instance()->teamServers->pluck('name')->all())
        ->toContain('noisy-box.example.test')
        ->not->toContain('quiet-box.example.test');
});

it('treats a server with a future silenced_from as active, not silenced', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)
        ->scheduledSilenceFrom(now()->addDays(7), now()->addDays(14))
        ->create(['name' => 'future-silenced-box.example.test']);

    $silencedTab = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('silencedFilter', 'silenced');

    $activeTab = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('silencedFilter', 'active');

    expect($silencedTab->instance()->teamServers->pluck('name')->all())
        ->not->toContain('future-silenced-box.example.test');
    expect($activeTab->instance()->teamServers->pluck('name')->all())
        ->toContain('future-silenced-box.example.test');
});

it('narrows the listing to a single team when a team filter is set', function () {
    $alice = User::factory()->create();
    $netservices = Team::factory()->create();
    $storage = Team::factory()->create();
    $alice->teams()->attach([$netservices->id, $storage->id]);

    Server::factory()->forTeam($netservices)->create(['name' => 'net-a.example.test']);
    Server::factory()->forTeam($storage)->create(['name' => 'storage-a.example.test']);

    $component = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('teamFilter', (string) $storage->id);

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('storage-a.example.test')
        ->not->toContain('net-a.example.test');
});

it('narrows the listing to a single OS when an OS filter is set', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'linux-box.example.test', 'os_type' => OsType::Linux]);
    Server::factory()->forTeam($team)->create(['name' => 'windows-box.example.test', 'os_type' => OsType::Windows]);
    Server::factory()->forTeam($team)->create(['name' => 'other-box.example.test', 'os_type' => OsType::Other]);

    $component = Livewire::actingAs($alice)->test(HomePage::class)->set('osFilter', 'windows');

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('windows-box.example.test')
        ->not->toContain('linux-box.example.test')
        ->not->toContain('other-box.example.test');
});

it('gives each tab its own paginator page name so they hold independent page state', function () {
    $alice = User::factory()->admin()->create();

    $component = Livewire::actingAs($alice)->test(HomePage::class);

    expect($component->instance()->teamServers->getPageName())->toBe('teamPage');
    expect($component->instance()->allServers->getPageName())->toBe('allPage');
    expect($component->instance()->alertingServers->getPageName())->toBe('alertingPage');
    expect($component->instance()->silencedServers->getPageName())->toBe('silencedPage');
});

it('paginates results according to the perPage selection', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->count(5)->forTeam($team)->create();

    $component = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('perPage', '2');

    $page = $component->instance()->teamServers;
    expect($page->perPage())->toBe(2);
    expect($page->count())->toBe(2);
    expect($page->total())->toBe(5);
    expect($page->lastPage())->toBe(3);
});

it('returns every server on a single page when perPage is set to "all"', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->count(7)->forTeam($team)->create();

    $component = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('perPage', 'all');

    $page = $component->instance()->teamServers;
    expect($page->count())->toBe(7);
    expect($page->lastPage())->toBe(1);
});

it('resets every tab back to page one when a filter changes', function () {
    $alice = User::factory()->admin()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->count(5)->forTeam($team)->create();

    $component = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('perPage', '2')
        ->call('setPage', 2, 'teamPage')
        ->call('setPage', 2, 'allPage')
        ->call('setPage', 2, 'alertingPage')
        ->call('setPage', 2, 'silencedPage');

    expect($component->instance()->teamServers->currentPage())->toBe(2);

    $component->set('filter', 'something-new');

    expect($component->instance()->teamServers->currentPage())->toBe(1);
    expect($component->instance()->allServers->currentPage())->toBe(1);
    expect($component->instance()->alertingServers->currentPage())->toBe(1);
    expect($component->instance()->silencedServers->currentPage())->toBe(1);
});
