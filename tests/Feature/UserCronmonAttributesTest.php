<?php

use App\Models\User;
use Carbon\CarbonInterface;

it('persists and casts the cronmon attributes on a user', function () {
    $user = User::factory()->create([
        'notification_email' => 'alerts@example.com',
        'sender_email' => 'noreply@example.com',
        'check_ins_require_token' => true,
        'silenced_until' => now()->addDay(),
        'silence_reason' => 'Building electrical work',
    ]);

    $user->refresh();

    expect($user->notification_email)->toBe('alerts@example.com')
        ->and($user->sender_email)->toBe('noreply@example.com')
        ->and($user->check_ins_require_token)->toBeTrue()
        ->and($user->silenced_until)->toBeInstanceOf(CarbonInterface::class)
        ->and($user->silence_reason)->toBe('Building electrical work');
});

it('silenced() factory state produces a user silenced into the future', function () {
    $user = User::factory()->silenced()->create();

    expect($user->silenced_until)->toBeInstanceOf(CarbonInterface::class)
        ->and($user->silenced_until->isFuture())->toBeTrue()
        ->and($user->silence_reason)->not->toBeNull();
});
