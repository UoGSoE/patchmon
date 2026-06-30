<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates a team-owned server when the user is a member of that team', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->postJson('/api/v1/servers', [
        'name' => 'team-backup.example.test',
        'team_id' => $team->id,
        'os_type' => 'linux',
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => 'days',
    ])->assertCreated();

    $server = Server::firstWhere('name', 'team-backup.example.test');
    expect($server)->not->toBeNull()
        ->and($server->team_id)->toBe($team->id)
        ->and($server->created_by_user_id)->toBe($alice->id);
});

it('refuses to create a server for a team the user is not in', function () {
    $alice = User::factory()->create();
    $foreignTeam = Team::factory()->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $this->postJson('/api/v1/servers', [
        'name' => 'Sneaky',
        'team_id' => $foreignTeam->id,
        'os_type' => 'linux',
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => 'days',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['team_id']);

    expect(Server::where('name', 'Sneaky')->count())->toBe(0);
});

it('rejects creating a server without write ability', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    Sanctum::actingAs($alice, ['servers:read']);

    $this->postJson('/api/v1/servers', [
        'name' => 'Nope',
        'team_id' => $team->id,
        'os_type' => 'linux',
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => 'days',
    ])->assertStatus(403);

    expect(Server::where('name', 'Nope')->count())->toBe(0);
});

it('requires a team_id', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['servers:write']);

    $this->postJson('/api/v1/servers', [
        'name' => 'Orphaned',
        'os_type' => 'linux',
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => 'days',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['team_id']);
});

it('persists the location field on the API create endpoint', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->postJson('/api/v1/servers', [
        'name' => 'located-server.example.test',
        'location' => 'Building-B',
        'team_id' => $team->id,
        'os_type' => 'linux',
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => 'days',
    ])->assertCreated()
        ->assertJsonPath('data.location', 'Building-B');

    $server = Server::firstWhere('name', 'located-server.example.test');
    expect($server->location)->toBe('Building-B');
});

it('rejects a name that is not a valid FQDN', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->postJson('/api/v1/servers', [
        'name' => 'not-an-fqdn',
        'team_id' => $team->id,
        'os_type' => 'linux',
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => 'days',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    expect(Server::count())->toBe(0);
});

it('lowercases and trims the name before saving', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->postJson('/api/v1/servers', [
        'name' => '  DC1.Eng.Example.AC.UK  ',
        'team_id' => $team->id,
        'os_type' => 'linux',
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => 'days',
    ])->assertCreated();

    expect(Server::firstWhere('name', 'dc1.eng.example.ac.uk'))->not->toBeNull();
});

it('rejects a duplicate server name on create', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    Server::factory()->forTeam($team)->create(['name' => 'taken.example.test']);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->postJson('/api/v1/servers', [
        'name' => 'taken.example.test',
        'team_id' => $team->id,
        'os_type' => 'linux',
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => 'days',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('accepts os_type windows or other', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    Sanctum::actingAs($alice, ['servers:write']);

    $this->postJson('/api/v1/servers', [
        'name' => 'windows-fileserver.example.test',
        'team_id' => $team->id,
        'os_type' => 'windows',
        'interval_months' => 1,
        'grace_value' => 2,
        'grace_units' => 'weeks',
    ])->assertCreated()
        ->assertJsonPath('data.os_type', 'windows');
});
