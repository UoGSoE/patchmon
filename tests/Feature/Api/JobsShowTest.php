<?php

use App\Models\Job;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 404 when the user cannot see the job', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $bob = User::factory()->create();
    $bobsJob = Job::factory()->forUser($bob)->create();
    Sanctum::actingAs($alice, ['jobs:read']);

    $this->getJson("/api/v1/jobs/{$bobsJob->id}")->assertStatus(404);
});

it('shows a job the user owns', function () {
    $alice = User::factory()->create();
    $job = Job::factory()->forUser($alice)->create(['name' => 'Mine']);
    Sanctum::actingAs($alice, ['jobs:read']);

    $this->getJson("/api/v1/jobs/{$job->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Mine')
        ->assertJsonPath('data.id', $job->id);
});
