<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 404 when deleting a server the user cannot see', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $team = Team::factory()->create();
    $server = Server::factory()->forTeam($team)->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $this->deleteJson("/api/v1/servers/{$server->id}")->assertStatus(404);

    expect(Server::find($server->id))->not->toBeNull();
});

it('deletes a server the user can see', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create();
    $bystander = Server::factory()->forTeam($team)->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $this->deleteJson("/api/v1/servers/{$server->id}")->assertNoContent();

    expect(Server::find($server->id))->toBeNull()
        ->and(Server::find($bystander->id))->not->toBeNull();
});
