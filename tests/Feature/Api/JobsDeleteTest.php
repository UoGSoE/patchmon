<?php

use App\Models\Job;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 404 when deleting a job the user cannot see', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $bob = User::factory()->create();
    $bobsJob = Job::factory()->forUser($bob)->create();
    Sanctum::actingAs($alice, ['jobs:write']);

    $this->deleteJson("/api/v1/jobs/{$bobsJob->id}")->assertStatus(404);

    expect(Job::find($bobsJob->id))->not->toBeNull();
});

it('deletes a job the user owns', function () {
    $alice = User::factory()->create();
    $job = Job::factory()->forUser($alice)->create();
    Sanctum::actingAs($alice, ['jobs:write']);

    $this->deleteJson("/api/v1/jobs/{$job->id}")->assertNoContent();

    expect(Job::find($job->id))->toBeNull();
});
