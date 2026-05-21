<?php

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Mail\ServerAwolNotification;
use App\Models\Server;
use Illuminate\Support\Facades\Mail;

it('does not alert silenced jobs even when they are overdue', function () {
    Mail::fake();

    $server = Server::factory()->silenced()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_patched_at' => now()->subHours(30),
        'alerting_since' => null,
        'last_alerted_at' => null,
    ]);

    $this->artisan('patchmon:evaluate')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($server->refresh()->alerting_since)->toBeNull()
        ->and($server->last_alerted_at)->toBeNull();
});

it('renders the awol email with key details about the job', function () {
    $server = Server::factory()->create([
        'name' => 'Nightly backup',
        'cron_expression' => '0 2 * * *',
        'schedule_interval' => null,
        'grace_value' => 10,
        'grace_units' => GraceUnit::Minutes,
        'last_patched_at' => now()->subHours(26),
        'alerting_since' => now()->subHours(2),
        'last_alerted_at' => now()->subHours(2),
    ]);

    $body = (new ServerAwolNotification($server))->render();

    expect($body)->toContain('Nightly backup')
        ->and($body)->toContain('0 2 * * *')
        ->and($body)->toContain('10 minutes');
});

it('leaves healthy jobs alone', function () {
    Mail::fake();

    $server = Server::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_patched_at' => now()->subMinutes(5),
        'alerting_since' => null,
        'last_alerted_at' => null,
    ]);

    $this->artisan('patchmon:evaluate')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($server->refresh()->alerting_since)->toBeNull();
});

it('does not re-alert an already-alerting job before its next schedule period plus grace has passed', function () {
    Mail::fake();

    $server = Server::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_patched_at' => now()->subDays(3),
        'alerting_since' => now()->subHours(6),
        'last_alerted_at' => now()->subHours(6),
    ]);

    $originalLastAlertedAt = $server->last_alerted_at;

    $this->artisan('patchmon:evaluate')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($server->refresh()->last_alerted_at->equalTo($originalLastAlertedAt))->toBeTrue();
});

it('re-alerts an already-alerting job once a full schedule period plus grace has elapsed since the last alert', function () {
    Mail::fake();

    $server = Server::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_patched_at' => now()->subDays(5),
        'alerting_since' => now()->subDays(4),
        'last_alerted_at' => now()->subHours(26),
    ]);

    $this->artisan('patchmon:evaluate')->assertSuccessful();

    Mail::assertQueued(ServerAwolNotification::class, fn ($mail) => $mail->server->is($server));
    expect($server->refresh()->last_alerted_at->diffInMinutes(now()))->toBeLessThan(1);
});

it('sends the alert to the resolved notification email', function () {
    Mail::fake();

    $server = Server::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_patched_at' => now()->subHours(30),
        'alerting_since' => null,
        'last_alerted_at' => null,
        'notification_email' => 'awol-alerts@example.test',
    ]);

    $this->artisan('patchmon:evaluate')->assertSuccessful();

    Mail::assertQueued(
        ServerAwolNotification::class,
        fn ($mail) => $mail->hasTo('awol-alerts@example.test')
    );
});

it('starts alerting and sends the first email when an overdue job has no alerting state yet', function () {
    Mail::fake();

    $server = Server::factory()->create([
        'schedule_interval' => ScheduleInterval::Daily,
        'schedule_frequency' => 1,
        'grace_value' => 1,
        'grace_units' => GraceUnit::Hours,
        'last_patched_at' => now()->subHours(30),
        'alerting_since' => null,
        'last_alerted_at' => null,
    ]);

    $this->artisan('patchmon:evaluate')->assertSuccessful();

    Mail::assertQueued(ServerAwolNotification::class, fn ($mail) => $mail->server->is($server));
    expect($server->refresh()->alerting_since)->not->toBeNull()
        ->and($server->last_alerted_at)->not->toBeNull();
});
