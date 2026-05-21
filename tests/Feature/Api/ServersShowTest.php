<?php

use App\Models\Server;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 404 when the user cannot see the job', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $bob = User::factory()->create();
    $bobsJob = Server::factory()->forUser($bob)->create();
    Sanctum::actingAs($alice, ['servers:read']);

    $this->getJson("/api/v1/servers/{$bobsJob->id}")->assertStatus(404);
});

it('shows a job the user owns', function () {
    $alice = User::factory()->create();
    $server = Server::factory()->forUser($alice)->create(['name' => 'Mine']);
    Sanctum::actingAs($alice, ['servers:read']);

    $this->getJson("/api/v1/servers/{$server->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Mine')
        ->assertJsonPath('data.id', $server->id);
});
