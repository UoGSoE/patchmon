<?php

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Models\Job;
use Illuminate\Support\Facades\Date;

it('treats a freshly created daily interval job that has never checked in as not overdue', function () {
    $job = Job::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_checked_in_at' => null,
    ]);

    expect($job->isOverdue())->toBeFalse();
});

it('is not overdue when an interval job checked in inside the period plus grace window', function () {
    $job = Job::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_checked_in_at' => now()->subHours(20),
    ]);

    expect($job->isOverdue())->toBeFalse();
});

it('is overdue when an interval job last checked in beyond the period plus grace window', function () {
    $job = Job::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_checked_in_at' => now()->subHours(30),
    ]);

    expect($job->isOverdue())->toBeTrue();
});

it('uses schedule_frequency to shorten the interval period', function () {
    $job = Job::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 4,
        'grace_value' => 30,
        'grace_units' => GraceUnit::Minutes,
        'last_checked_in_at' => now()->subHours(8),
    ]);

    expect($job->isOverdue())->toBeTrue();
});

it('is not overdue when a cron job checked in after its most recent expected firing', function () {
    $job = Job::factory()->withCron('*/5 * * * *')->create([
        'grace_value' => 1,
        'grace_units' => GraceUnit::Minutes,
        'last_checked_in_at' => now()->subMinute(),
    ]);

    expect($job->isOverdue())->toBeFalse();
});

it('is overdue when a cron job missed its expected firing by more than the grace period', function () {
    Date::setTestNow('2026-05-19 14:30:00');

    $job = Job::factory()->withCron('0 * * * *')->create([
        'grace_value' => 5,
        'grace_units' => GraceUnit::Minutes,
        'last_checked_in_at' => Date::parse('2026-05-19 13:00:30'),
    ]);

    expect($job->isOverdue())->toBeTrue();
});

it('is not overdue when a cron job is still inside the grace window after its expected firing', function () {
    Date::setTestNow('2026-05-19 14:02:00');

    $job = Job::factory()->withCron('0 * * * *')->create([
        'grace_value' => 5,
        'grace_units' => GraceUnit::Minutes,
        'last_checked_in_at' => Date::parse('2026-05-19 13:00:30'),
    ]);

    expect($job->isOverdue())->toBeFalse();
});

it('computes the next scheduled run after a reference time for an interval job', function () {
    $job = Job::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 4,
        'cron_expression' => null,
    ]);

    $reference = Date::parse('2026-05-19 12:00:00');

    expect($job->nextScheduledAfter($reference)->toIso8601String())
        ->toBe(Date::parse('2026-05-19 18:00:00')->toIso8601String());
});

it('computes the next scheduled run after a reference time for a cron job', function () {
    $job = Job::factory()->withCron('0 */2 * * *')->create();

    $reference = Date::parse('2026-05-19 12:30:00');

    expect($job->nextScheduledAfter($reference)->toIso8601String())
        ->toBe(Date::parse('2026-05-19 14:00:00')->toIso8601String());
});

it('treats a never-checked-in interval job that was created more than a period plus grace ago as overdue', function () {
    $job = Job::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_checked_in_at' => null,
    ]);

    $job->created_at = now()->subDays(2);
    $job->save();

    expect($job->isOverdue())->toBeTrue();
});
