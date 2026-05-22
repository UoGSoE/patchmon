<?php

use App\Enums\GraceUnit;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 404 when patching a server the user cannot see', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $team = Team::factory()->create();
    $server = Server::factory()->forTeam($team)->create(['name' => 'hands-off.example.test']);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->patchJson("/api/v1/servers/{$server->id}", ['name' => 'mine-now.example.test'])->assertStatus(404);

    expect($server->fresh()->name)->toBe('hands-off.example.test');
});

it('patches a server the user can see', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create([
        'name' => 'old-name.example.test',
        'description' => 'Old desc',
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
    ]);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->patchJson("/api/v1/servers/{$server->id}", [
        'name' => 'new-name.example.test',
        'grace_value' => 14,
    ])->assertOk();

    $fresh = $server->fresh();
    expect($fresh->name)->toBe('new-name.example.test')
        ->and($fresh->description)->toBe('Old desc')
        ->and($fresh->grace_value)->toBe(14);
});

it('rejects an update with an invalid FQDN name', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create(['name' => 'starter.example.test']);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->patchJson("/api/v1/servers/{$server->id}", ['name' => 'not-an-fqdn'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    expect($server->fresh()->name)->toBe('starter.example.test');
});

it('allows an update that keeps the same name (ignores self in unique check)', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create(['name' => 'keepme.example.test']);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->patchJson("/api/v1/servers/{$server->id}", [
        'name' => 'keepme.example.test',
        'grace_value' => 14,
    ])->assertOk();

    expect($server->fresh()->grace_value)->toBe(14);
});

it('rejects an update that collides with another existing server name', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    Server::factory()->forTeam($team)->create(['name' => 'other.example.test']);
    $server = Server::factory()->forTeam($team)->create(['name' => 'mine.example.test']);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->patchJson("/api/v1/servers/{$server->id}", ['name' => 'other.example.test'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    expect($server->fresh()->name)->toBe('mine.example.test');
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
