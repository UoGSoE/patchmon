<?php

use App\Models\ActivityLog;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('logs creating a server via the API', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->postJson('/api/v1/servers', [
        'name' => 'api-made.example.test',
        'team_id' => $team->id,
        'os_type' => 'linux',
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => 'days',
    ])->assertCreated();

    $server = Server::firstWhere('name', 'api-made.example.test');
    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($alice->id);
    expect($log->server_id)->toBe($server->id);
    expect($log->description)->toBe('Created the server');
    expect($log->source_ip)->not->toBeNull();
});

it('logs updating a server via the API', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team, $alice)->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $this->patchJson("/api/v1/servers/{$server->id}", ['description' => 'new desc'])->assertOk();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($alice->id);
    expect($log->server_id)->toBe($server->id);
    expect($log->description)->toBe('Updated the server');
});

it('logs deleting a server via the API, keeping the name in the description', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team, $alice)->create(['name' => 'api-doomed.example.test']);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->deleteJson("/api/v1/servers/{$server->id}")->assertNoContent();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($alice->id);
    expect($log->description)->toContain('api-doomed.example.test');
    expect($log->description)->toContain('Deleted');
});
