<?php

use App\Models\User;
use Carbon\CarbonInterface;

it('persists and casts the cronmon attributes on a user', function () {
    $user = User::factory()->create([
        'notification_email' => 'alerts@example.com',
        'sender_email' => 'noreply@example.com',
        'silenced_until' => now()->addDay(),
        'silence_reason' => 'Building electrical work',
    ]);

    $user->refresh();

    expect($user->notification_email)->toBe('alerts@example.com')
        ->and($user->sender_email)->toBe('noreply@example.com')
        ->and($user->silenced_until)->toBeInstanceOf(CarbonInterface::class)
        ->and($user->silence_reason)->toBe('Building electrical work');
});

it('silenceUntil and unsilence round-trip on a user', function () {
    $user = User::factory()->create();
    $until = now()->addDay()->startOfSecond();

    $user->silenceUntil($until, 'On leave');

    $user->refresh();
    expect($user->silenced_until->equalTo($until))->toBeTrue()
        ->and($user->silence_reason)->toBe('On leave');

    $user->unsilence();
    $user->refresh();
    expect($user->silenced_until)->toBeNull()
        ->and($user->silence_reason)->toBeNull();
});

it('silenced() factory state produces a user silenced into the future', function () {
    $user = User::factory()->silenced()->create();

    expect($user->silenced_until)->toBeInstanceOf(CarbonInterface::class)
        ->and($user->silenced_until->isFuture())->toBeTrue()
        ->and($user->silence_reason)->not->toBeNull();
});
