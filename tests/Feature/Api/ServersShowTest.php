<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 404 when the user is not a member of the team owning the server', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $team = Team::factory()->create();
    $server = Server::factory()->forTeam($team)->create();
    Sanctum::actingAs($alice, ['servers:read']);

    $this->getJson("/api/v1/servers/{$server->id}")->assertStatus(404);
});

it('shows a server in a team the user belongs to', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create(['name' => 'Mine']);
    Sanctum::actingAs($alice, ['servers:read']);

    $this->getJson("/api/v1/servers/{$server->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Mine')
        ->assertJsonPath('data.id', $server->id);
});

it('does not expose patch_token in the API response', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create();
    Sanctum::actingAs($alice, ['servers:read']);

    $this->getJson("/api/v1/servers/{$server->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.patch_token');
});
