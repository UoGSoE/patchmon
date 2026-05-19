<?php

use App\Models\CheckIn;
use App\Models\Job;
use Carbon\CarbonInterface;

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
