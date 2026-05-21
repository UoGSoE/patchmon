<?php

use App\Models\Server;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 404 listing check-ins for a job the user cannot see', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $bob = User::factory()->create();
    $bobsJob = Server::factory()->forUser($bob)->create();
    Sanctum::actingAs($alice, ['servers:read']);

    $this->getJson("/api/v1/servers/{$bobsJob->id}/patch-events")->assertStatus(404);
});

it('lists check-ins newest first for a job the user can see', function () {
    $alice = User::factory()->create();
    $server = Server::factory()->forUser($alice)->create();
    $oldest = $server->patchEvents()->create(['patched_at' => now()->subHours(3)]);
    $middle = $server->patchEvents()->create(['patched_at' => now()->subHours(2)]);
    $newest = $server->patchEvents()->create(['patched_at' => now()->subHour()]);
    Sanctum::actingAs($alice, ['servers:read']);

    $response = $this->getJson("/api/v1/servers/{$server->id}/patch-events")->assertOk();

    $ids = collect($response->json('patch_events.data'))->pluck('id')->all();
    expect($ids)->toEqual([$newest->id, $middle->id, $oldest->id]);
});
