<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('unsilences a previously silenced server', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->silenced()->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $this->deleteJson("/api/v1/servers/{$server->id}/silence")->assertOk();

    $fresh = $server->fresh();
    expect($fresh->silenced_until)->toBeNull()
        ->and($fresh->silence_reason)->toBeNull();
});

it('silencing twice is idempotent and overwrites with the latest values', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $first = now()->addDay()->startOfSecond();
    $second = now()->addDays(3)->startOfSecond();

    $this->postJson("/api/v1/servers/{$server->id}/silence", [
        'silenced_until' => $first->toIso8601String(),
        'silence_reason' => 'short',
    ])->assertOk();

    $this->postJson("/api/v1/servers/{$server->id}/silence", [
        'silenced_until' => $second->toIso8601String(),
        'silence_reason' => 'longer',
    ])->assertOk();

    $fresh = $server->fresh();
    expect($fresh->silenced_until->equalTo($second))->toBeTrue()
        ->and($fresh->silence_reason)->toBe('longer');
});

it('silences a server until a future moment', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create([
        'silenced_until' => null,
        'silence_reason' => null,
    ]);
    Sanctum::actingAs($alice, ['servers:write']);

    $until = now()->addDay()->startOfSecond();

    $this->postJson("/api/v1/servers/{$server->id}/silence", [
        'silenced_until' => $until->toIso8601String(),
        'silence_reason' => 'Building works',
    ])->assertOk();

    $fresh = $server->fresh();
    expect($fresh->silenced_until->equalTo($until))->toBeTrue()
        ->and($fresh->silence_reason)->toBe('Building works');
});
