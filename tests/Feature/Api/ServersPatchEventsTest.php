<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 404 listing patch events for a server the user cannot see', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $team = Team::factory()->create();
    $server = Server::factory()->forTeam($team)->create();
    Sanctum::actingAs($alice, ['servers:read']);

    $this->getJson("/api/v1/servers/{$server->id}/patch-events")->assertStatus(404);
});

it('lists patch events newest first for a server the user can see', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create();
    $oldest = $server->patchEvents()->create(['patched_at' => now()->subDays(20)]);
    $middle = $server->patchEvents()->create(['patched_at' => now()->subDays(10)]);
    $newest = $server->patchEvents()->create(['patched_at' => now()->subDay()]);
    Sanctum::actingAs($alice, ['servers:read']);

    $response = $this->getJson("/api/v1/servers/{$server->id}/patch-events")->assertOk();

    $ids = collect($response->json('patch_events.data'))->pluck('id')->all();
    expect($ids)->toEqual([$newest->id, $middle->id, $oldest->id]);
});
