<?php

use App\Models\Job;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 404 when patching a job the user cannot see', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $bob = User::factory()->create();
    $bobsJob = Job::factory()->forUser($bob)->create(['name' => 'Hands off']);
    Sanctum::actingAs($alice, ['jobs:write']);

    $this->patchJson("/api/v1/jobs/{$bobsJob->id}", ['name' => 'Mine now'])->assertStatus(404);

    expect($bobsJob->fresh()->name)->toBe('Hands off');
});

it('patches a job the user owns', function () {
    $alice = User::factory()->create();
    $job = Job::factory()->forUser($alice)->create([
        'name' => 'Old name',
        'description' => 'Old desc',
        'grace_value' => 5,
        'grace_units' => 'minutes',
    ]);
    Sanctum::actingAs($alice, ['jobs:write']);

    $this->patchJson("/api/v1/jobs/{$job->id}", [
        'name' => 'New name',
        'grace_value' => 30,
    ])->assertOk();

    $fresh = $job->fresh();
    expect($fresh->name)->toBe('New name')
        ->and($fresh->description)->toBe('Old desc')
        ->and($fresh->grace_value)->toBe(30);
});
