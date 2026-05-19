<?php

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Mail\JobAwolNotification;
use App\Models\Job;
use Illuminate\Support\Facades\Mail;

it('does not alert silenced jobs even when they are overdue', function () {
    Mail::fake();

    $job = Job::factory()->silenced()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_checked_in_at' => now()->subHours(30),
        'alerting_since' => null,
        'last_alerted_at' => null,
    ]);

    $this->artisan('cronmon:evaluate')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($job->refresh()->alerting_since)->toBeNull()
        ->and($job->last_alerted_at)->toBeNull();
});

it('renders the awol email with key details about the job', function () {
    $job = Job::factory()->create([
        'name' => 'Nightly backup',
        'cron_expression' => '0 2 * * *',
        'schedule_interval' => null,
        'grace_value' => 10,
        'grace_units' => GraceUnit::Minutes,
        'last_checked_in_at' => now()->subHours(26),
        'alerting_since' => now()->subHours(2),
        'last_alerted_at' => now()->subHours(2),
    ]);

    $body = (new JobAwolNotification($job))->render();

    expect($body)->toContain('Nightly backup')
        ->and($body)->toContain('0 2 * * *')
        ->and($body)->toContain('10 minutes');
});

it('leaves healthy jobs alone', function () {
    Mail::fake();

    $job = Job::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_checked_in_at' => now()->subMinutes(5),
        'alerting_since' => null,
        'last_alerted_at' => null,
    ]);

    $this->artisan('cronmon:evaluate')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($job->refresh()->alerting_since)->toBeNull();
});

it('does not re-alert an already-alerting job before its next schedule period plus grace has passed', function () {
    Mail::fake();

    $job = Job::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_checked_in_at' => now()->subDays(3),
        'alerting_since' => now()->subHours(6),
        'last_alerted_at' => now()->subHours(6),
    ]);

    $originalLastAlertedAt = $job->last_alerted_at;

    $this->artisan('cronmon:evaluate')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($job->refresh()->last_alerted_at->equalTo($originalLastAlertedAt))->toBeTrue();
});

it('re-alerts an already-alerting job once a full schedule period plus grace has elapsed since the last alert', function () {
    Mail::fake();

    $job = Job::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_checked_in_at' => now()->subDays(5),
        'alerting_since' => now()->subDays(4),
        'last_alerted_at' => now()->subHours(26),
    ]);

    $this->artisan('cronmon:evaluate')->assertSuccessful();

    Mail::assertQueued(JobAwolNotification::class, fn ($mail) => $mail->job->is($job));
    expect($job->refresh()->last_alerted_at->diffInMinutes(now()))->toBeLessThan(1);
});

it('sends the alert to the resolved notification email', function () {
    Mail::fake();

    $job = Job::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_checked_in_at' => now()->subHours(30),
        'alerting_since' => null,
        'last_alerted_at' => null,
        'notification_email' => 'awol-alerts@example.test',
    ]);

    $this->artisan('cronmon:evaluate')->assertSuccessful();

    Mail::assertQueued(
        JobAwolNotification::class,
        fn ($mail) => $mail->hasTo('awol-alerts@example.test')
    );
});

it('starts alerting and sends the first email when an overdue job has no alerting state yet', function () {
    Mail::fake();

    $job = Job::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_checked_in_at' => now()->subHours(30),
        'alerting_since' => null,
        'last_alerted_at' => null,
    ]);

    $this->artisan('cronmon:evaluate')->assertSuccessful();

    Mail::assertQueued(JobAwolNotification::class, fn ($mail) => $mail->job->is($job));
    expect($job->refresh()->alerting_since)->not->toBeNull()
        ->and($job->last_alerted_at)->not->toBeNull();
});
