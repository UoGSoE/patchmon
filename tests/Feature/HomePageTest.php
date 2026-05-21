<?php

use App\Livewire\HomePage;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('shows the signed-in user their own jobs on the My jobs tab', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $aliceServer = Server::factory()->forUser($alice)->create(['name' => 'Alice nightly backup']);
    $bobServer = Server::factory()->forUser($bob)->create(['name' => 'Bob nightly backup']);

    Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->assertSee('Alice nightly backup')
        ->assertDontSee('Bob nightly backup');
});

it('shows jobs from teams the user belongs to and excludes other teams', function () {
    $alice = User::factory()->create();
    $netservices = Team::factory()->create();
    $storage = Team::factory()->create();
    $alice->teams()->attach($netservices);

    $myTeamServer = Server::factory()->forTeam($netservices)->create(['name' => 'Network DNS export']);
    $otherTeamServer = Server::factory()->forTeam($storage)->create(['name' => 'Storage tape rotation']);

    Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('tab', 'teams')
        ->assertSee('Network DNS export')
        ->assertDontSee('Storage tape rotation');
});

it('surfaces alerting jobs above healthy ones, then sorts the healthy lot alphabetically', function () {
    $alice = User::factory()->create();

    Server::factory()->forUser($alice)->create(['name' => 'Zebra healthy job']);
    Server::factory()->forUser($alice)->create(['name' => 'Apple healthy job']);
    Server::factory()->forUser($alice)->create([
        'name' => 'Recently alerting',
        'alerting_since' => now()->subMinutes(10),
    ]);
    Server::factory()->forUser($alice)->create([
        'name' => 'Long alerting',
        'alerting_since' => now()->subHours(8),
    ]);

    $component = Livewire::actingAs($alice)->test(HomePage::class);
    $orderedNames = $component->instance()->myServers->pluck('name')->all();

    expect($orderedNames)->toBe([
        'Recently alerting',
        'Long alerting',
        'Apple healthy job',
        'Zebra healthy job',
    ]);
});

it('shows jobs from every team the user is a member of', function () {
    $alice = User::factory()->create();
    $netservices = Team::factory()->create();
    $storage = Team::factory()->create();
    $alice->teams()->attach([$netservices->id, $storage->id]);

    Server::factory()->forTeam($netservices)->create(['name' => 'Network DNS export']);
    Server::factory()->forTeam($storage)->create(['name' => 'Storage tape rotation']);

    Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('tab', 'teams')
        ->assertSee('Network DNS export')
        ->assertSee('Storage tape rotation');
});

it('shows the right empty state when the user has no personal jobs and no teams', function () {
    $alice = User::factory()->create();

    $component = Livewire::actingAs($alice)->test(HomePage::class);

    $component->assertSee('No personal servers yet.')
        ->set('tab', 'teams')
        ->assertSee('You are not a member of any teams.');
});

it('redirects unauthenticated visitors away from the home page', function () {
    $this->get('/')->assertRedirect();
});

it('shows alerting jobs from the users own and team jobs on the Alerting tab', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $alice->teams()->attach($aliceTeam);

    Server::factory()->forUser($alice)->alerting()->create(['name' => 'Alice alerting personal']);
    Server::factory()->forUser($alice)->create(['name' => 'Alice healthy personal']);
    Server::factory()->forTeam($aliceTeam)->alerting()->create(['name' => 'Alice team alerting']);
    Server::factory()->forUser($bob)->alerting()->create(['name' => 'Bob alerting']);
    Server::factory()->forTeam($otherTeam)->alerting()->create(['name' => 'Other team alerting']);

    $component = Livewire::actingAs($alice)->test(HomePage::class);
    $alertingNames = $component->instance()->alertingServers->pluck('name')->all();

    expect($alertingNames)->toContain('Alice alerting personal')
        ->toContain('Alice team alerting')
        ->not->toContain('Alice healthy personal')
        ->not->toContain('Bob alerting')
        ->not->toContain('Other team alerting');
});

it('shows every alerting job across the system to admins on the Alerting tab', function () {
    $admin = User::factory()->admin()->create();
    $someoneElse = User::factory()->create();
    $unrelatedTeam = Team::factory()->create();

    Server::factory()->forUser($someoneElse)->alerting()->create(['name' => 'Stranger alerting personal']);
    Server::factory()->forTeam($unrelatedTeam)->alerting()->create(['name' => 'Stranger team alerting']);
    Server::factory()->forUser($someoneElse)->create(['name' => 'Stranger healthy']);

    $component = Livewire::actingAs($admin)->test(HomePage::class);
    $alertingNames = $component->instance()->alertingServers->pluck('name')->all();

    expect($alertingNames)->toContain('Stranger alerting personal')
        ->toContain('Stranger team alerting')
        ->not->toContain('Stranger healthy');
});

it('filters jobs by a fragment of the name on every tab', function () {
    $alice = User::factory()->admin()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forUser($alice)->alerting()->create(['name' => 'Personal linux alerting']);
    Server::factory()->forUser($alice)->create(['name' => 'Personal linux healthy']);
    Server::factory()->forUser($alice)->create(['name' => 'Personal windows healthy']);
    Server::factory()->forTeam($team)->create(['name' => 'Team linux mirror']);
    Server::factory()->forTeam($team)->create(['name' => 'Team windows mirror']);

    $component = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'linux');

    expect($component->instance()->myServers->pluck('name')->all())
        ->toContain('Personal linux alerting')
        ->toContain('Personal linux healthy')
        ->not->toContain('Personal windows healthy');
    expect($component->instance()->teamServers->pluck('name')->all())
        ->toContain('Team linux mirror')
        ->not->toContain('Team windows mirror');
    expect($component->instance()->alertingServers->pluck('name')->all())
        ->toContain('Personal linux alerting');
});

it('also matches the filter against the description', function () {
    $alice = User::factory()->create();

    Server::factory()->forUser($alice)->create([
        'name' => 'Nightly job',
        'description' => 'rsnapshot of the linux fleet',
    ]);
    Server::factory()->forUser($alice)->create([
        'name' => 'Other job',
        'description' => 'curl against the order API',
    ]);

    $component = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'linux');

    expect($component->instance()->myServers->pluck('name')->all())
        ->toContain('Nightly job')
        ->not->toContain('Other job');
});

it('ignores filter strings that are blank or only one character', function () {
    $alice = User::factory()->create();

    Server::factory()->forUser($alice)->create(['name' => 'Alpha job']);
    Server::factory()->forUser($alice)->create(['name' => 'Beta job']);

    $blank = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', '   ');
    expect($blank->instance()->myServers->pluck('name')->all())
        ->toContain('Alpha job')
        ->toContain('Beta job');

    $singleChar = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'a');
    expect($singleChar->instance()->myServers->pluck('name')->all())
        ->toContain('Alpha job')
        ->toContain('Beta job');
});

it('inverts the filter when the exclude checkbox is ticked', function () {
    $alice = User::factory()->create();

    Server::factory()->forUser($alice)->create(['name' => 'Personal linux backup', 'description' => null]);
    Server::factory()->forUser($alice)->create(['name' => 'Personal windows backup', 'description' => null]);
    Server::factory()->forUser($alice)->create([
        'name' => 'Nightly probe',
        'description' => 'targets the linux fleet',
    ]);

    $component = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('filter', 'linux')
        ->set('excludeFilter', true);

    expect($component->instance()->myServers->pluck('name')->all())
        ->toContain('Personal windows backup')
        ->not->toContain('Personal linux backup')
        ->not->toContain('Nightly probe');
});

it('matches the filter against the location column', function () {
    $alice = User::factory()->create();

    Server::factory()->forUser($alice)->create(['name' => 'Site job', 'location' => 'Rankine']);
    Server::factory()->forUser($alice)->create(['name' => 'Other site job', 'location' => 'JWS']);
    Server::factory()->forUser($alice)->create(['name' => 'No location job', 'location' => null]);

    $component = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'rankine');

    expect($component->instance()->myServers->pluck('name')->all())
        ->toContain('Site job')
        ->not->toContain('Other site job')
        ->not->toContain('No location job');
});

it('requires every whitespace-separated token to match in include mode', function () {
    $alice = User::factory()->create();

    Server::factory()->forUser($alice)->create(['name' => 'linux backup', 'location' => 'Rankine']);
    Server::factory()->forUser($alice)->create(['name' => 'linux mirror', 'location' => 'JWS']);
    Server::factory()->forUser($alice)->create(['name' => 'windows backup', 'location' => 'Rankine']);
    Server::factory()->forUser($alice)->create(['name' => 'unrelated', 'location' => 'MDR']);

    $component = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('filter', 'linux rankine');

    expect($component->instance()->myServers->pluck('name')->all())
        ->toContain('linux backup')
        ->not->toContain('linux mirror')
        ->not->toContain('windows backup')
        ->not->toContain('unrelated');
});

it('hides rows matching any token in exclude mode', function () {
    $alice = User::factory()->create();

    Server::factory()->forUser($alice)->create(['name' => 'linux only', 'location' => null]);
    Server::factory()->forUser($alice)->create(['name' => 'backup only', 'location' => null]);
    Server::factory()->forUser($alice)->create(['name' => 'rankine only', 'location' => 'Rankine']);
    Server::factory()->forUser($alice)->create(['name' => 'clean job', 'location' => 'JWS']);

    $component = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('filter', 'linux rankine')
        ->set('excludeFilter', true);

    expect($component->instance()->myServers->pluck('name')->all())
        ->toContain('backup only')
        ->toContain('clean job')
        ->not->toContain('linux only')
        ->not->toContain('rankine only');
});
