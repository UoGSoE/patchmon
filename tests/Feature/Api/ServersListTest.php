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

    Server::factory()->forTeam($myTeam)->create(['name' => 'myteamserver.example.test']);
    Server::factory()->forTeam($otherTeam)->create(['name' => 'otherteamserver.example.test']);

    Sanctum::actingAs($alice, ['servers:read']);

    $response = $this->getJson('/api/v1/servers')->assertOk();

    $names = collect($response->json('servers.data'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['myteamserver.example.test']);
});

it('shows admins every server regardless of team membership', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    Server::factory()->forTeam($teamA)->create(['name' => 'a.example.test']);
    Server::factory()->forTeam($teamB)->create(['name' => 'b.example.test']);

    Sanctum::actingAs($admin, ['servers:read']);

    $response = $this->getJson('/api/v1/servers')->assertOk();
    $names = collect($response->json('servers.data'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['a.example.test', 'b.example.test']);
});

it('filters the list by location', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'rankine-server.example.test', 'location' => 'Rankine']);
    Server::factory()->forTeam($team)->create(['name' => 'jws-server.example.test', 'location' => 'JWS']);
    Server::factory()->forTeam($team)->create(['name' => 'no-location-server.example.test', 'location' => null]);

    Sanctum::actingAs($alice, ['servers:read']);

    $response = $this->getJson('/api/v1/servers?filter[location]=rankine')->assertOk();
    $names = collect($response->json('servers.data'))->pluck('name')->all();

    expect($names)->toContain('rankine-server.example.test')
        ->not->toContain('jws-server.example.test')
        ->not->toContain('no-location-server.example.test');
});

it('filters the list by os_type', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'lx.example.test', 'os_type' => 'linux']);
    Server::factory()->forTeam($team)->create(['name' => 'win.example.test', 'os_type' => 'windows']);
    Server::factory()->forTeam($team)->create(['name' => 'oth.example.test', 'os_type' => 'other']);

    Sanctum::actingAs($alice, ['servers:read']);

    $response = $this->getJson('/api/v1/servers?filter[os_type]=windows')->assertOk();
    $names = collect($response->json('servers.data'))->pluck('name')->all();

    expect($names)->toEqual(['win.example.test']);
});
