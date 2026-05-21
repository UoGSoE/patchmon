<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects an unknown scope with 400', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['servers:read']);

    $this->getJson('/api/v1/servers?scope=wibble')
        ->assertStatus(400)
        ->assertJsonFragment(['message' => 'scope must be one of: mine, teams, all.']);
});

it('rejects an unknown filter with 400', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['servers:read']);

    $this->getJson('/api/v1/servers?filter[wibble]=foo')->assertStatus(400);
});

it('refuses /api/v1/servers without the jobs:read ability', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $this->getJson('/api/v1/servers')->assertStatus(403);
});

it('defaults scope to all and shows only jobs the user can see', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $bob = User::factory()->create();
    $myTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $alice->teams()->attach($myTeam);

    Server::factory()->forUser($alice)->create(['name' => 'Mine']);
    Server::factory()->forTeam($myTeam)->create(['name' => 'MyTeamJob']);
    Server::factory()->forUser($bob)->create(['name' => 'Bobs']);
    Server::factory()->forTeam($otherTeam)->create(['name' => 'OtherTeamJob']);

    Sanctum::actingAs($alice, ['servers:read']);

    $response = $this->getJson('/api/v1/servers')->assertOk();

    $names = collect($response->json('servers.data'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['Mine', 'MyTeamJob'])
        ->and($response->json('scope'))->toBe('all');
});

it('lists only team jobs the user can see when scope=teams', function () {
    $alice = User::factory()->create();
    $myTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $alice->teams()->attach($myTeam);

    Server::factory()->forUser($alice)->create(['name' => 'Mine']);
    Server::factory()->forTeam($myTeam)->create(['name' => 'MyTeamJob']);
    Server::factory()->forTeam($otherTeam)->create(['name' => 'OtherTeamJob']);

    Sanctum::actingAs($alice, ['servers:read']);

    $response = $this->getJson('/api/v1/servers?scope=teams')->assertOk();

    $names = collect($response->json('servers.data'))->pluck('name')->all();
    expect($names)->toEqual(['MyTeamJob']);
});

it('lists only the users personal jobs when scope=mine', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forUser($alice)->create(['name' => 'Mine']);
    Server::factory()->forUser($bob)->create(['name' => 'Bobs']);
    Server::factory()->forTeam($team)->create(['name' => 'TeamJob']);

    Sanctum::actingAs($alice, ['servers:read']);

    $response = $this->getJson('/api/v1/servers?scope=mine')->assertOk();

    $names = collect($response->json('servers.data'))->pluck('name')->all();
    expect($names)->toEqual(['Mine'])
        ->and($response->json('scope'))->toBe('mine');
});

it('filters the list by location', function () {
    $alice = User::factory()->create();
    Server::factory()->forUser($alice)->create(['name' => 'rankine job', 'location' => 'Rankine']);
    Server::factory()->forUser($alice)->create(['name' => 'jws job', 'location' => 'JWS']);
    Server::factory()->forUser($alice)->create(['name' => 'no-location job', 'location' => null]);
    Sanctum::actingAs($alice, ['servers:read']);

    $response = $this->getJson('/api/v1/servers?filter[location]=rankine')->assertOk();
    $names = collect($response->json('servers.data'))->pluck('name')->all();

    expect($names)->toContain('rankine job')
        ->not->toContain('jws job')
        ->not->toContain('no-location job');
});
