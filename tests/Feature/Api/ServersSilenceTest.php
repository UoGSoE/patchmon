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
    expect($fresh->silenced_from)->toBeNull()
        ->and($fresh->silenced_until)->toBeNull()
        ->and($fresh->silence_reason)->toBeNull();
});

it('unsilencing an already-unsilenced server is harmless', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create([
        'silenced_from' => null,
        'silenced_until' => null,
        'silence_reason' => null,
    ]);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->deleteJson("/api/v1/servers/{$server->id}/silence")->assertOk();

    $fresh = $server->fresh();
    expect($fresh->silenced_from)->toBeNull()
        ->and($fresh->silenced_until)->toBeNull()
        ->and($fresh->silence_reason)->toBeNull();
});

it('silencing twice is idempotent and overwrites with the latest values', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $firstFrom = now()->startOfSecond();
    $firstUntil = now()->addDay()->startOfSecond();
    $secondFrom = now()->addDay()->startOfSecond();
    $secondUntil = now()->addDays(3)->startOfSecond();

    $this->postJson("/api/v1/servers/{$server->id}/silence", [
        'silenced_from' => $firstFrom->toIso8601String(),
        'silenced_until' => $firstUntil->toIso8601String(),
        'silence_reason' => 'short',
    ])->assertOk();

    $this->postJson("/api/v1/servers/{$server->id}/silence", [
        'silenced_from' => $secondFrom->toIso8601String(),
        'silenced_until' => $secondUntil->toIso8601String(),
        'silence_reason' => 'longer',
    ])->assertOk();

    $fresh = $server->fresh();
    expect($fresh->silenced_from->equalTo($secondFrom))->toBeTrue()
        ->and($fresh->silenced_until->equalTo($secondUntil))->toBeTrue()
        ->and($fresh->silence_reason)->toBe('longer');
});

it('silences a server between two future moments', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create([
        'silenced_from' => null,
        'silenced_until' => null,
        'silence_reason' => null,
    ]);
    Sanctum::actingAs($alice, ['servers:write']);

    $from = now()->startOfSecond();
    $until = now()->addDay()->startOfSecond();

    $this->postJson("/api/v1/servers/{$server->id}/silence", [
        'silenced_from' => $from->toIso8601String(),
        'silenced_until' => $until->toIso8601String(),
        'silence_reason' => 'Building works',
    ])->assertOk();

    $fresh = $server->fresh();
    expect($fresh->silenced_from->equalTo($from))->toBeTrue()
        ->and($fresh->silenced_until->equalTo($until))->toBeTrue()
        ->and($fresh->silence_reason)->toBe('Building works');
});
