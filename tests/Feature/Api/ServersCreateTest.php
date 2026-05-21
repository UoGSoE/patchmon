<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates a team-owned job when the user is a member of that team', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->postJson('/api/v1/servers', [
        'name' => 'Team backup',
        'team_id' => $team->id,
        'cron_expression' => '0 2 * * *',
        'grace_value' => 5,
        'grace_units' => 'minutes',
    ])->assertCreated();

    $server = Server::firstWhere('name', 'Team backup');
    expect($server)->not->toBeNull()
        ->and($server->team_id)->toBe($team->id)
        ->and($server->user_id)->toBeNull()
        ->and($server->created_by_user_id)->toBe($alice->id);
});

it('refuses to create a job for a team the user is not in', function () {
    $alice = User::factory()->create();
    $foreignTeam = Team::factory()->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $this->postJson('/api/v1/servers', [
        'name' => 'Sneaky',
        'team_id' => $foreignTeam->id,
        'cron_expression' => '0 2 * * *',
        'grace_value' => 5,
        'grace_units' => 'minutes',
    ])->assertStatus(422);

    expect(Server::where('name', 'Sneaky')->count())->toBe(0);
});

it('rejects creating a job without write ability', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['servers:read']);

    $this->postJson('/api/v1/servers', [
        'name' => 'Nope',
        'schedule_interval' => 'daily',
        'schedule_frequency' => 1,
        'grace_value' => 5,
        'grace_units' => 'minutes',
    ])->assertStatus(403);

    expect(Server::where('name', 'Nope')->count())->toBe(0);
});

it('creates a personal interval job for the authenticated user', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $this->postJson('/api/v1/servers', [
        'name' => 'Nightly backup',
        'description' => 'Backs up the database overnight.',
        'schedule_interval' => 'daily',
        'schedule_frequency' => 1,
        'grace_value' => 30,
        'grace_units' => 'minutes',
    ])->assertCreated();

    $server = Server::firstWhere('name', 'Nightly backup');
    expect($server)->not->toBeNull()
        ->and($server->user_id)->toBe($alice->id)
        ->and($server->team_id)->toBeNull()
        ->and($server->created_by_user_id)->toBe($alice->id)
        ->and($server->grace_value)->toBe(30);
});

it('persists the location field on the API create endpoint', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $this->postJson('/api/v1/servers', [
        'name' => 'Located backup',
        'location' => 'Rankine',
        'schedule_interval' => 'daily',
        'schedule_frequency' => 1,
        'grace_value' => 5,
        'grace_units' => 'minutes',
    ])->assertCreated()
        ->assertJsonPath('data.location', 'Rankine');

    $server = Server::firstWhere('name', 'Located backup');
    expect($server->location)->toBe('Rankine');
});
