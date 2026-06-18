<?php

use App\Enums\GraceUnit;
use App\Mail\ServerOverdueNotification;
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

it('does not alert a server with no team even when it is overdue', function () {
    Mail::fake();

    $server = Server::factory()->unassigned()->overdue()->create();

    $this->artisan('patchmon:evaluate')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($server->refresh()->alerting_since)->toBeNull();
});

it('does not alert an inactive server even when it is overdue', function () {
    Mail::fake();

    $server = Server::factory()->inactive()->overdue()->create();

    $this->artisan('patchmon:evaluate')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($server->refresh()->alerting_since)->toBeNull();
});

it('renders the overdue email with key details about the server', function () {
    $server = Server::factory()->create([
        'name' => 'fileserver-prod-02',
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
        'last_patched_at' => now()->subMonths(2),
        'alerting_since' => now()->subDays(3),
        'last_alerted_at' => now()->subDays(3),
    ]);

    $body = (new ServerOverdueNotification($server))->render();

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

    Mail::assertQueued(ServerOverdueNotification::class, fn ($mail) => $mail->server->is($server));
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
        'notification_email' => 'overdue-alerts@example.test',
    ]);

    $this->artisan('patchmon:evaluate')->assertSuccessful();

    Mail::assertQueued(
        ServerOverdueNotification::class,
        fn ($mail) => $mail->hasTo('overdue-alerts@example.test')
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

    Mail::assertQueued(ServerOverdueNotification::class, fn ($mail) => $mail->server->is($server));
    expect($server->refresh()->alerting_since)->not->toBeNull()
        ->and($server->last_alerted_at)->not->toBeNull();
});
