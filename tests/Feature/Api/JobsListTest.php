<?php

use App\Models\Job;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects an unknown scope with 400', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['jobs:read']);

    $this->getJson('/api/v1/jobs?scope=wibble')
        ->assertStatus(400)
        ->assertJsonFragment(['message' => 'scope must be one of: mine, teams, all.']);
});

it('rejects an unknown filter with 400', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['jobs:read']);

    $this->getJson('/api/v1/jobs?filter[wibble]=foo')->assertStatus(400);
});

it('refuses /api/v1/jobs without the jobs:read ability', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['jobs:write']);

    $this->getJson('/api/v1/jobs')->assertStatus(403);
});

it('defaults scope to all and shows only jobs the user can see', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $bob = User::factory()->create();
    $myTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $alice->teams()->attach($myTeam);

    Job::factory()->forUser($alice)->create(['name' => 'Mine']);
    Job::factory()->forTeam($myTeam)->create(['name' => 'MyTeamJob']);
    Job::factory()->forUser($bob)->create(['name' => 'Bobs']);
    Job::factory()->forTeam($otherTeam)->create(['name' => 'OtherTeamJob']);

    Sanctum::actingAs($alice, ['jobs:read']);

    $response = $this->getJson('/api/v1/jobs')->assertOk();

    $names = collect($response->json('jobs.data'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['Mine', 'MyTeamJob'])
        ->and($response->json('scope'))->toBe('all');
});

it('lists only team jobs the user can see when scope=teams', function () {
    $alice = User::factory()->create();
    $myTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $alice->teams()->attach($myTeam);

    Job::factory()->forUser($alice)->create(['name' => 'Mine']);
    Job::factory()->forTeam($myTeam)->create(['name' => 'MyTeamJob']);
    Job::factory()->forTeam($otherTeam)->create(['name' => 'OtherTeamJob']);

    Sanctum::actingAs($alice, ['jobs:read']);

    $response = $this->getJson('/api/v1/jobs?scope=teams')->assertOk();

    $names = collect($response->json('jobs.data'))->pluck('name')->all();
    expect($names)->toEqual(['MyTeamJob']);
});

it('lists only the users personal jobs when scope=mine', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Job::factory()->forUser($alice)->create(['name' => 'Mine']);
    Job::factory()->forUser($bob)->create(['name' => 'Bobs']);
    Job::factory()->forTeam($team)->create(['name' => 'TeamJob']);

    Sanctum::actingAs($alice, ['jobs:read']);

    $response = $this->getJson('/api/v1/jobs?scope=mine')->assertOk();

    $names = collect($response->json('jobs.data'))->pluck('name')->all();
    expect($names)->toEqual(['Mine'])
        ->and($response->json('scope'))->toBe('mine');
});
