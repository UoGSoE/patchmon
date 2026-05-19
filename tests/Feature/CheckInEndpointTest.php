<?php

use App\Jobs\RecordCheckIn;
use App\Models\Job;
use Illuminate\Support\Facades\Queue;

it('returns 404 and queues nothing when the token does not match any job', function () {
    Queue::fake();

    Job::factory()->create();

    $response = $this->get('/check-in/00000000-0000-0000-0000-000000000000');

    $response->assertNotFound();
    Queue::assertNothingPushed();
});

it('still queues a check-in for a silenced job so forensic history stays complete', function () {
    Queue::fake();

    $job = Job::factory()->silenced()->create();

    $response = $this->get('/check-in/'.$job->check_in_token);

    $response->assertOk();
    Queue::assertPushed(RecordCheckIn::class, fn (RecordCheckIn $queued) => $queued->jobId === $job->id);
});

it('records the check-in against the right job and timestamp when the queued job runs', function () {
    $job = Job::factory()->alerting()->create();
    $at = now()->subSeconds(30)->startOfSecond();

    (new RecordCheckIn($job->id, '198.51.100.7', $at))->handle();

    $job->refresh();

    expect($job->checkIns)->toHaveCount(1)
        ->and($job->checkIns->first()->source_ip)->toBe('198.51.100.7')
        ->and($job->last_checked_in_at->equalTo($at))->toBeTrue()
        ->and($job->alerting_since)->toBeNull();
});

it('queues a check-in job when a job receives a ping at its token URL', function () {
    Queue::fake();

    $job = Job::factory()->create();

    $response = $this->get('/check-in/'.$job->check_in_token);

    $response->assertOk();
    Queue::assertPushed(RecordCheckIn::class, fn (RecordCheckIn $queued) => $queued->jobId === $job->id);
});
