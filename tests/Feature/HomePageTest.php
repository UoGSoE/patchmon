<?php

use App\Enums\OsType;
use App\Livewire\HomePage;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('shows servers from teams the user belongs to and excludes other teams', function () {
    $alice = User::factory()->create();
    $netservices = Team::factory()->create();
    $storage = Team::factory()->create();
    $alice->teams()->attach($netservices);

    Server::factory()->forTeam($netservices)->create(['name' => 'Network DNS export']);
    Server::factory()->forTeam($storage)->create(['name' => 'Storage tape rotation']);

    Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->assertSee('Network DNS export')
        ->assertDontSee('Storage tape rotation');
});

it('surfaces alerting servers above healthy ones, then sorts the healthy lot alphabetically', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'Zebra healthy']);
    Server::factory()->forTeam($team)->create(['name' => 'Apple healthy']);
    Server::factory()->forTeam($team)->create([
        'name' => 'Recently alerting',
        'alerting_since' => now()->subMinutes(10),
    ]);
    Server::factory()->forTeam($team)->create([
        'name' => 'Long alerting',
        'alerting_since' => now()->subHours(8),
    ]);

    $component = Livewire::actingAs($alice)->test(HomePage::class);
    $orderedNames = $component->instance()->teamServers->pluck('name')->all();

    expect($orderedNames)->toBe([
        'Recently alerting',
        'Long alerting',
        'Apple healthy',
        'Zebra healthy',
    ]);
});

it('shows servers from every team the user is a member of', function () {
    $alice = User::factory()->create();
    $netservices = Team::factory()->create();
    $storage = Team::factory()->create();
    $alice->teams()->attach([$netservices->id, $storage->id]);

    Server::factory()->forTeam($netservices)->create(['name' => 'Network DNS export']);
    Server::factory()->forTeam($storage)->create(['name' => 'Storage tape rotation']);

    Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->assertSee('Network DNS export')
        ->assertSee('Storage tape rotation');
});

it('shows the right empty state when the user has no teams', function () {
    $alice = User::factory()->create();

    Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->assertSee('You are not a member of any teams.');
});

it('redirects unauthenticated visitors away from the home page', function () {
    $this->get('/')->assertRedirect();
});

it('shows alerting servers from the users teams on the Alerting tab', function () {
    $alice = User::factory()->create();
    $aliceTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $alice->teams()->attach($aliceTeam);

    Server::factory()->forTeam($aliceTeam)->alerting()->create(['name' => 'Alice team alerting']);
    Server::factory()->forTeam($aliceTeam)->create(['name' => 'Alice team healthy']);
    Server::factory()->forTeam($otherTeam)->alerting()->create(['name' => 'Other team alerting']);

    $component = Livewire::actingAs($alice)->test(HomePage::class);
    $alertingNames = $component->instance()->alertingServers->pluck('name')->all();

    expect($alertingNames)->toContain('Alice team alerting')
        ->not->toContain('Alice team healthy')
        ->not->toContain('Other team alerting');
});

it('shows every alerting server across the system to admins on the Alerting tab', function () {
    $admin = User::factory()->admin()->create();
    $unrelatedTeam = Team::factory()->create();

    Server::factory()->forTeam($unrelatedTeam)->alerting()->create(['name' => 'Stranger team alerting']);
    Server::factory()->forTeam($unrelatedTeam)->create(['name' => 'Stranger team healthy']);

    $component = Livewire::actingAs($admin)->test(HomePage::class);
    $alertingNames = $component->instance()->alertingServers->pluck('name')->all();

    expect($alertingNames)->toContain('Stranger team alerting')
        ->not->toContain('Stranger team healthy');
});

it('filters servers by a fragment of the name on every tab', function () {
    $alice = User::factory()->admin()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->alerting()->create(['name' => 'Team linux alerting']);
    Server::factory()->forTeam($team)->create(['name' => 'Team linux healthy']);
    Server::factory()->forTeam($team)->create(['name' => 'Team windows healthy']);

    $component = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'linux');

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('Team linux alerting')
        ->toContain('Team linux healthy')
        ->not->toContain('Team windows healthy');
    expect($component->instance()->alertingServers->pluck('name')->all())
        ->toContain('Team linux alerting');
});

it('also matches the filter against the description', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create([
        'name' => 'Nightly server',
        'description' => 'rsnapshot of the linux fleet',
    ]);
    Server::factory()->forTeam($team)->create([
        'name' => 'Other server',
        'description' => 'curl against the order API',
    ]);

    $component = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'linux');

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('Nightly server')
        ->not->toContain('Other server');
});

it('ignores filter strings that are blank or only one character', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'Alpha server']);
    Server::factory()->forTeam($team)->create(['name' => 'Beta server']);

    $blank = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', '   ');
    expect($blank->instance()->teamServers->pluck('name')->all())
        ->toContain('Alpha server')
        ->toContain('Beta server');

    $singleChar = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'a');
    expect($singleChar->instance()->teamServers->pluck('name')->all())
        ->toContain('Alpha server')
        ->toContain('Beta server');
});

it('inverts the filter when the exclude checkbox is ticked', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'Team linux backup', 'description' => null]);
    Server::factory()->forTeam($team)->create(['name' => 'Team windows backup', 'description' => null]);
    Server::factory()->forTeam($team)->create([
        'name' => 'Nightly probe',
        'description' => 'targets the linux fleet',
    ]);

    $component = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('filter', 'linux')
        ->set('excludeFilter', true);

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('Team windows backup')
        ->not->toContain('Team linux backup')
        ->not->toContain('Nightly probe');
});

it('matches the filter against the location column', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'Site server', 'location' => 'Rankine']);
    Server::factory()->forTeam($team)->create(['name' => 'Other site server', 'location' => 'JWS']);
    Server::factory()->forTeam($team)->create(['name' => 'No location server', 'location' => null]);

    $component = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'rankine');

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('Site server')
        ->not->toContain('Other site server')
        ->not->toContain('No location server');
});

it('requires every whitespace-separated token to match in include mode', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'linux backup', 'location' => 'Rankine']);
    Server::factory()->forTeam($team)->create(['name' => 'linux mirror', 'location' => 'JWS']);
    Server::factory()->forTeam($team)->create(['name' => 'windows backup', 'location' => 'Rankine']);
    Server::factory()->forTeam($team)->create(['name' => 'unrelated', 'location' => 'MDR']);

    $component = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('filter', 'linux rankine');

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('linux backup')
        ->not->toContain('linux mirror')
        ->not->toContain('windows backup')
        ->not->toContain('unrelated');
});

it('hides rows matching any token in exclude mode', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'linux only', 'location' => null]);
    Server::factory()->forTeam($team)->create(['name' => 'backup only', 'location' => null]);
    Server::factory()->forTeam($team)->create(['name' => 'rankine only', 'location' => 'Rankine']);
    Server::factory()->forTeam($team)->create(['name' => 'clean server', 'location' => 'JWS']);

    $component = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('filter', 'linux rankine')
        ->set('excludeFilter', true);

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('backup only')
        ->toContain('clean server')
        ->not->toContain('linux only')
        ->not->toContain('rankine only');
});

it('narrows the listing by silenced state', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->silenced()->create(['name' => 'Quiet box']);
    Server::factory()->forTeam($team)->create(['name' => 'Noisy box']);

    $onlySilenced = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('silencedFilter', 'silenced');

    expect($onlySilenced->instance()->teamServers->pluck('name')->all())
        ->toContain('Quiet box')
        ->not->toContain('Noisy box');

    $onlyActive = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('silencedFilter', 'active');

    expect($onlyActive->instance()->teamServers->pluck('name')->all())
        ->toContain('Noisy box')
        ->not->toContain('Quiet box');
});

it('treats a server with a future silenced_from as active, not silenced', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)
        ->scheduledSilenceFrom(now()->addDays(7), now()->addDays(14))
        ->create(['name' => 'Future-silenced box']);

    $silencedTab = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('silencedFilter', 'silenced');

    $activeTab = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('silencedFilter', 'active');

    expect($silencedTab->instance()->teamServers->pluck('name')->all())
        ->not->toContain('Future-silenced box');
    expect($activeTab->instance()->teamServers->pluck('name')->all())
        ->toContain('Future-silenced box');
});

it('narrows the listing to a single team when a team filter is set', function () {
    $alice = User::factory()->create();
    $netservices = Team::factory()->create();
    $storage = Team::factory()->create();
    $alice->teams()->attach([$netservices->id, $storage->id]);

    Server::factory()->forTeam($netservices)->create(['name' => 'Net A']);
    Server::factory()->forTeam($storage)->create(['name' => 'Storage A']);

    $component = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('teamFilter', (string) $storage->id);

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('Storage A')
        ->not->toContain('Net A');
});

it('narrows the listing to a single OS when an OS filter is set', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'Linux box', 'os_type' => OsType::Linux]);
    Server::factory()->forTeam($team)->create(['name' => 'Windows box', 'os_type' => OsType::Windows]);
    Server::factory()->forTeam($team)->create(['name' => 'Other box', 'os_type' => OsType::Other]);

    $component = Livewire::actingAs($alice)->test(HomePage::class)->set('osFilter', 'windows');

    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('Windows box')
        ->not->toContain('Linux box')
        ->not->toContain('Other box');
});
