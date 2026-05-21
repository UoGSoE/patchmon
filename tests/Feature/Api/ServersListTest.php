<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects an unknown filter with 400', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['servers:read']);

    $this->getJson('/api/v1/servers?filter[wibble]=foo')->assertStatus(400);
});

it('refuses /api/v1/servers without the servers:read ability', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $this->getJson('/api/v1/servers')->assertStatus(403);
});

it('shows only servers from teams the user belongs to', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $myTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $alice->teams()->attach($myTeam);

    Server::factory()->forTeam($myTeam)->create(['name' => 'MyTeamServer']);
    Server::factory()->forTeam($otherTeam)->create(['name' => 'OtherTeamServer']);

    Sanctum::actingAs($alice, ['servers:read']);

    $response = $this->getJson('/api/v1/servers')->assertOk();

    $names = collect($response->json('servers.data'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['MyTeamServer']);
});

it('shows admins every server regardless of team membership', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    Server::factory()->forTeam($teamA)->create(['name' => 'A']);
    Server::factory()->forTeam($teamB)->create(['name' => 'B']);

    Sanctum::actingAs($admin, ['servers:read']);

    $response = $this->getJson('/api/v1/servers')->assertOk();
    $names = collect($response->json('servers.data'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['A', 'B']);
});

it('filters the list by location', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'rankine server', 'location' => 'Rankine']);
    Server::factory()->forTeam($team)->create(['name' => 'jws server', 'location' => 'JWS']);
    Server::factory()->forTeam($team)->create(['name' => 'no-location server', 'location' => null]);

    Sanctum::actingAs($alice, ['servers:read']);

    $response = $this->getJson('/api/v1/servers?filter[location]=rankine')->assertOk();
    $names = collect($response->json('servers.data'))->pluck('name')->all();

    expect($names)->toContain('rankine server')
        ->not->toContain('jws server')
        ->not->toContain('no-location server');
});

it('filters the list by os_type', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'lx', 'os_type' => 'linux']);
    Server::factory()->forTeam($team)->create(['name' => 'win', 'os_type' => 'windows']);
    Server::factory()->forTeam($team)->create(['name' => 'oth', 'os_type' => 'other']);

    Sanctum::actingAs($alice, ['servers:read']);

    $response = $this->getJson('/api/v1/servers?filter[os_type]=windows')->assertOk();
    $names = collect($response->json('servers.data'))->pluck('name')->all();

    expect($names)->toEqual(['win']);
});
