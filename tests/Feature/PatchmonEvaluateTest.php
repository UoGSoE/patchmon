<?php

use App\Enums\GraceUnit;
use App\Mail\ServerAwolNotification;
use App\Models\Server;
use Illuminate\Support\Facades\Mail;

it('does not alert silenced servers even when they are overdue', function () {
    Mail::fake();

    $server = Server::factory()->silenced()->create([
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
        'last_patched_at' => now()->subMonths(2),
        'alerting_since' => null,
        'last_alerted_at' => null,
    ]);

    $this->artisan('patchmon:evaluate')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($server->refresh()->alerting_since)->toBeNull()
        ->and($server->last_alerted_at)->toBeNull();
});

it('renders the awol email with key details about the server', function () {
    $server = Server::factory()->create([
        'name' => 'fileserver-prod-02',
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
        'last_patched_at' => now()->subMonths(2),
        'alerting_since' => now()->subDays(3),
        'last_alerted_at' => now()->subDays(3),
    ]);

    $body = (new ServerAwolNotification($server))->render();

    expect($body)->toContain('fileserver-prod-02')
        ->and($body)->toContain('7 days');
});

it('leaves healthy servers alone', function () {
    Mail::fake();

    Server::factory()->create([
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
        'last_patched_at' => now()->subDays(5),
        'alerting_since' => null,
        'last_alerted_at' => null,
    ]);

    $this->artisan('patchmon:evaluate')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('does not re-alert an already-alerting server inside the weekly throttle window', function () {
    Mail::fake();

    $server = Server::factory()->create([
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
        'last_patched_at' => now()->subMonths(2),
        'alerting_since' => now()->subDays(10),
        'last_alerted_at' => now()->subDays(2),
    ]);

    $originalLastAlertedAt = $server->last_alerted_at;

    $this->artisan('patchmon:evaluate')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($server->refresh()->last_alerted_at->equalTo($originalLastAlertedAt))->toBeTrue();
});

it('re-alerts an already-alerting server once a week has passed since the last alert', function () {
    Mail::fake();

    $server = Server::factory()->create([
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
        'last_patched_at' => now()->subMonths(3),
        'alerting_since' => now()->subDays(20),
        'last_alerted_at' => now()->subDays(8),
    ]);

    $this->artisan('patchmon:evaluate')->assertSuccessful();

    Mail::assertQueued(ServerAwolNotification::class, fn ($mail) => $mail->server->is($server));
    expect($server->refresh()->last_alerted_at->diffInMinutes(now()))->toBeLessThan(1);
});

it('sends the alert to the resolved notification email', function () {
    Mail::fake();

    Server::factory()->create([
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
        'last_patched_at' => now()->subMonths(2),
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

it('starts alerting and sends the first email when an overdue server has no alerting state yet', function () {
    Mail::fake();

    $server = Server::factory()->create([
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
        'last_patched_at' => now()->subMonths(2),
        'alerting_since' => null,
        'last_alerted_at' => null,
    ]);

    $this->artisan('patchmon:evaluate')->assertSuccessful();

    Mail::assertQueued(ServerAwolNotification::class, fn ($mail) => $mail->server->is($server));
    expect($server->refresh()->alerting_since)->not->toBeNull()
        ->and($server->last_alerted_at)->not->toBeNull();
});
