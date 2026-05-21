<?php

use App\Models\Server;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 404 when deleting a job the user cannot see', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $bob = User::factory()->create();
    $bobsJob = Server::factory()->forUser($bob)->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $this->deleteJson("/api/v1/servers/{$bobsJob->id}")->assertStatus(404);

    expect(Server::find($bobsJob->id))->not->toBeNull();
});

it('deletes a job the user owns', function () {
    $alice = User::factory()->create();
    $server = Server::factory()->forUser($alice)->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $this->deleteJson("/api/v1/servers/{$server->id}")->assertNoContent();

    expect(Server::find($server->id))->toBeNull();
});
