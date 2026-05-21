<?php

use App\Enums\GraceUnit;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 404 when patching a server the user cannot see', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $team = Team::factory()->create();
    $server = Server::factory()->forTeam($team)->create(['name' => 'Hands off']);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->patchJson("/api/v1/servers/{$server->id}", ['name' => 'Mine now'])->assertStatus(404);

    expect($server->fresh()->name)->toBe('Hands off');
});

it('patches a server the user can see', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create([
        'name' => 'Old name',
        'description' => 'Old desc',
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
    ]);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->patchJson("/api/v1/servers/{$server->id}", [
        'name' => 'New name',
        'grace_value' => 14,
    ])->assertOk();

    $fresh = $server->fresh();
    expect($fresh->name)->toBe('New name')
        ->and($fresh->description)->toBe('Old desc')
        ->and($fresh->grace_value)->toBe(14);
});

it('updates a server location and can clear it via the API', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create(['location' => 'Rankine']);
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
