<?php

use App\Livewire\HomePage;
use App\Models\Job;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('shows the signed-in user their own jobs on the My jobs tab', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $aliceJob = Job::factory()->forUser($alice)->create(['name' => 'Alice nightly backup']);
    $bobJob = Job::factory()->forUser($bob)->create(['name' => 'Bob nightly backup']);

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

    $myTeamJob = Job::factory()->forTeam($netservices)->create(['name' => 'Network DNS export']);
    $otherTeamJob = Job::factory()->forTeam($storage)->create(['name' => 'Storage tape rotation']);

    Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('tab', 'teams')
        ->assertSee('Network DNS export')
        ->assertDontSee('Storage tape rotation');
});

it('surfaces alerting jobs above healthy ones, then sorts the healthy lot alphabetically', function () {
    $alice = User::factory()->create();

    Job::factory()->forUser($alice)->create(['name' => 'Zebra healthy job']);
    Job::factory()->forUser($alice)->create(['name' => 'Apple healthy job']);
    Job::factory()->forUser($alice)->create([
        'name' => 'Recently alerting',
        'alerting_since' => now()->subMinutes(10),
    ]);
    Job::factory()->forUser($alice)->create([
        'name' => 'Long alerting',
        'alerting_since' => now()->subHours(8),
    ]);

    $component = Livewire::actingAs($alice)->test(HomePage::class);
    $orderedNames = $component->instance()->myJobs->pluck('name')->all();

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

    Job::factory()->forTeam($netservices)->create(['name' => 'Network DNS export']);
    Job::factory()->forTeam($storage)->create(['name' => 'Storage tape rotation']);

    Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('tab', 'teams')
        ->assertSee('Network DNS export')
        ->assertSee('Storage tape rotation');
});

it('shows the right empty state when the user has no personal jobs and no teams', function () {
    $alice = User::factory()->create();

    $component = Livewire::actingAs($alice)->test(HomePage::class);

    $component->assertSee('No personal jobs yet.')
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

    Job::factory()->forUser($alice)->alerting()->create(['name' => 'Alice alerting personal']);
    Job::factory()->forUser($alice)->create(['name' => 'Alice healthy personal']);
    Job::factory()->forTeam($aliceTeam)->alerting()->create(['name' => 'Alice team alerting']);
    Job::factory()->forUser($bob)->alerting()->create(['name' => 'Bob alerting']);
    Job::factory()->forTeam($otherTeam)->alerting()->create(['name' => 'Other team alerting']);

    $component = Livewire::actingAs($alice)->test(HomePage::class);
    $alertingNames = $component->instance()->alertingJobs->pluck('name')->all();

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

    Job::factory()->forUser($someoneElse)->alerting()->create(['name' => 'Stranger alerting personal']);
    Job::factory()->forTeam($unrelatedTeam)->alerting()->create(['name' => 'Stranger team alerting']);
    Job::factory()->forUser($someoneElse)->create(['name' => 'Stranger healthy']);

    $component = Livewire::actingAs($admin)->test(HomePage::class);
    $alertingNames = $component->instance()->alertingJobs->pluck('name')->all();

    expect($alertingNames)->toContain('Stranger alerting personal')
        ->toContain('Stranger team alerting')
        ->not->toContain('Stranger healthy');
});

it('filters jobs by a fragment of the name on every tab', function () {
    $alice = User::factory()->admin()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Job::factory()->forUser($alice)->alerting()->create(['name' => 'Personal linux alerting']);
    Job::factory()->forUser($alice)->create(['name' => 'Personal linux healthy']);
    Job::factory()->forUser($alice)->create(['name' => 'Personal windows healthy']);
    Job::factory()->forTeam($team)->create(['name' => 'Team linux mirror']);
    Job::factory()->forTeam($team)->create(['name' => 'Team windows mirror']);

    $component = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'linux');

    expect($component->instance()->myJobs->pluck('name')->all())
        ->toContain('Personal linux alerting')
        ->toContain('Personal linux healthy')
        ->not->toContain('Personal windows healthy');
    expect($component->instance()->teamJobs->pluck('name')->all())
        ->toContain('Team linux mirror')
        ->not->toContain('Team windows mirror');
    expect($component->instance()->alertingJobs->pluck('name')->all())
        ->toContain('Personal linux alerting');
});

it('also matches the filter against the description', function () {
    $alice = User::factory()->create();

    Job::factory()->forUser($alice)->create([
        'name' => 'Nightly job',
        'description' => 'rsnapshot of the linux fleet',
    ]);
    Job::factory()->forUser($alice)->create([
        'name' => 'Other job',
        'description' => 'curl against the order API',
    ]);

    $component = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'linux');

    expect($component->instance()->myJobs->pluck('name')->all())
        ->toContain('Nightly job')
        ->not->toContain('Other job');
});

it('ignores filter strings that are blank or only one character', function () {
    $alice = User::factory()->create();

    Job::factory()->forUser($alice)->create(['name' => 'Alpha job']);
    Job::factory()->forUser($alice)->create(['name' => 'Beta job']);

    $blank = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', '   ');
    expect($blank->instance()->myJobs->pluck('name')->all())
        ->toContain('Alpha job')
        ->toContain('Beta job');

    $singleChar = Livewire::actingAs($alice)->test(HomePage::class)->set('filter', 'a');
    expect($singleChar->instance()->myJobs->pluck('name')->all())
        ->toContain('Alpha job')
        ->toContain('Beta job');
});

it('inverts the filter when the exclude checkbox is ticked', function () {
    $alice = User::factory()->create();

    Job::factory()->forUser($alice)->create(['name' => 'Personal linux backup', 'description' => null]);
    Job::factory()->forUser($alice)->create(['name' => 'Personal windows backup', 'description' => null]);
    Job::factory()->forUser($alice)->create([
        'name' => 'Nightly probe',
        'description' => 'targets the linux fleet',
    ]);

    $component = Livewire::actingAs($alice)
        ->test(HomePage::class)
        ->set('filter', 'linux')
        ->set('excludeFilter', true);

    expect($component->instance()->myJobs->pluck('name')->all())
        ->toContain('Personal windows backup')
        ->not->toContain('Personal linux backup')
        ->not->toContain('Nightly probe');
});
