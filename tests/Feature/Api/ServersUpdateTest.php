<?php

use App\Models\Server;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 404 when patching a job the user cannot see', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $bob = User::factory()->create();
    $bobsJob = Server::factory()->forUser($bob)->create(['name' => 'Hands off']);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->patchJson("/api/v1/servers/{$bobsJob->id}", ['name' => 'Mine now'])->assertStatus(404);

    expect($bobsJob->fresh()->name)->toBe('Hands off');
});

it('patches a job the user owns', function () {
    $alice = User::factory()->create();
    $server = Server::factory()->forUser($alice)->create([
        'name' => 'Old name',
        'description' => 'Old desc',
        'grace_value' => 5,
        'grace_units' => 'minutes',
    ]);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->patchJson("/api/v1/servers/{$server->id}", [
        'name' => 'New name',
        'grace_value' => 30,
    ])->assertOk();

    $fresh = $server->fresh();
    expect($fresh->name)->toBe('New name')
        ->and($fresh->description)->toBe('Old desc')
        ->and($fresh->grace_value)->toBe(30);
});

it('updates a job location and can clear it via the API', function () {
    $alice = User::factory()->create();
    $server = Server::factory()->forUser($alice)->create(['location' => 'Rankine']);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->patchJson("/api/v1/servers/{$server->id}", ['location' => 'Joseph Black'])
        ->assertOk()
        ->assertJsonPath('data.location', 'Joseph Black');
    expect($server->fresh()->location)->toBe('Joseph Black');

    $this->patchJson("/api/v1/servers/{$server->id}", ['location' => null])
        ->assertOk()
        ->assertJsonPath('data.location', null);
    expect($server->fresh()->location)->toBeNull();
});
