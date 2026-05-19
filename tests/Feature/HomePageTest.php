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
