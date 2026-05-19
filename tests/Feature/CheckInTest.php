<?php

use App\Models\CheckIn;
use App\Models\Job;
use Carbon\CarbonInterface;

it('recordCheckIn stamps the check-in with an explicit timestamp when one is provided', function () {
    $job = Job::factory()->create();
    $requestedAt = now()->subSeconds(30)->startOfSecond();

    $checkIn = $job->recordCheckIn('203.0.113.5', $requestedAt);

    $job->refresh();

    expect($checkIn->checked_in_at->equalTo($requestedAt))->toBeTrue()
        ->and($job->last_checked_in_at->equalTo($requestedAt))->toBeTrue();
});

it('recordCheckIn logs the ping and clears any alerting state on the job', function () {
    $job = Job::factory()->alerting()->create([
        'last_checked_in_at' => now()->subDay(),
    ]);

    $job->recordCheckIn('203.0.113.5');

    $job->refresh();

    expect($job->checkIns)->toHaveCount(1)
        ->and($job->checkIns->first()->source_ip)->toBe('203.0.113.5')
        ->and($job->last_checked_in_at->diffInSeconds(now()))->toBeLessThan(2)
        ->and($job->alerting_since)->toBeNull()
        ->and($job->last_alerted_at)->toBeNull();
});

it('records a check-in against a job and exposes it via the relation', function () {
    $job = Job::factory()->create();

    $checkIn = CheckIn::factory()->for($job)->create([
        'source_ip' => '10.0.0.42',
    ]);

    expect($checkIn->job->is($job))->toBeTrue()
        ->and($checkIn->checked_in_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($checkIn->source_ip)->toBe('10.0.0.42')
        ->and($job->fresh()->checkIns->pluck('id'))->toContain($checkIn->id);
});
