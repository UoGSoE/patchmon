<?php

use App\Models\Job;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 404 listing check-ins for a job the user cannot see', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $bob = User::factory()->create();
    $bobsJob = Job::factory()->forUser($bob)->create();
    Sanctum::actingAs($alice, ['jobs:read']);

    $this->getJson("/api/v1/jobs/{$bobsJob->id}/check-ins")->assertStatus(404);
});

it('lists check-ins newest first for a job the user can see', function () {
    $alice = User::factory()->create();
    $job = Job::factory()->forUser($alice)->create();
    $oldest = $job->checkIns()->create(['checked_in_at' => now()->subHours(3)]);
    $middle = $job->checkIns()->create(['checked_in_at' => now()->subHours(2)]);
    $newest = $job->checkIns()->create(['checked_in_at' => now()->subHour()]);
    Sanctum::actingAs($alice, ['jobs:read']);

    $response = $this->getJson("/api/v1/jobs/{$job->id}/check-ins")->assertOk();

    $ids = collect($response->json('check_ins.data'))->pluck('id')->all();
    expect($ids)->toEqual([$newest->id, $middle->id, $oldest->id]);
});
