<?php

use App\Models\Job;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates a team-owned job when the user is a member of that team', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    Sanctum::actingAs($alice, ['jobs:write']);

    $this->postJson('/api/v1/jobs', [
        'name' => 'Team backup',
        'team_id' => $team->id,
        'cron_expression' => '0 2 * * *',
        'grace_value' => 5,
        'grace_units' => 'minutes',
    ])->assertCreated();

    $job = Job::firstWhere('name', 'Team backup');
    expect($job)->not->toBeNull()
        ->and($job->team_id)->toBe($team->id)
        ->and($job->user_id)->toBeNull()
        ->and($job->created_by_user_id)->toBe($alice->id);
});

it('refuses to create a job for a team the user is not in', function () {
    $alice = User::factory()->create();
    $foreignTeam = Team::factory()->create();
    Sanctum::actingAs($alice, ['jobs:write']);

    $this->postJson('/api/v1/jobs', [
        'name' => 'Sneaky',
        'team_id' => $foreignTeam->id,
        'cron_expression' => '0 2 * * *',
        'grace_value' => 5,
        'grace_units' => 'minutes',
    ])->assertStatus(422);

    expect(Job::where('name', 'Sneaky')->count())->toBe(0);
});

it('rejects creating a job without write ability', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['jobs:read']);

    $this->postJson('/api/v1/jobs', [
        'name' => 'Nope',
        'schedule_interval' => 'daily',
        'schedule_frequency' => 1,
        'grace_value' => 5,
        'grace_units' => 'minutes',
    ])->assertStatus(403);

    expect(Job::where('name', 'Nope')->count())->toBe(0);
});

it('creates a personal interval job for the authenticated user', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['jobs:write']);

    $this->postJson('/api/v1/jobs', [
        'name' => 'Nightly backup',
        'description' => 'Backs up the database overnight.',
        'schedule_interval' => 'daily',
        'schedule_frequency' => 1,
        'grace_value' => 30,
        'grace_units' => 'minutes',
    ])->assertCreated();

    $job = Job::firstWhere('name', 'Nightly backup');
    expect($job)->not->toBeNull()
        ->and($job->user_id)->toBe($alice->id)
        ->and($job->team_id)->toBeNull()
        ->and($job->created_by_user_id)->toBe($alice->id)
        ->and($job->grace_value)->toBe(30);
});

it('persists the location field on the API create endpoint', function () {
    $alice = User::factory()->create();
    Sanctum::actingAs($alice, ['jobs:write']);

    $this->postJson('/api/v1/jobs', [
        'name' => 'Located backup',
        'location' => 'Rankine',
        'schedule_interval' => 'daily',
        'schedule_frequency' => 1,
        'grace_value' => 5,
        'grace_units' => 'minutes',
    ])->assertCreated()
        ->assertJsonPath('data.location', 'Rankine');

    $job = Job::firstWhere('name', 'Located backup');
    expect($job->location)->toBe('Rankine');
});
