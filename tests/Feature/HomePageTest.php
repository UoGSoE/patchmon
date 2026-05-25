<?php

use App\Enums\OsType;
use App\Livewire\HomePage;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

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
