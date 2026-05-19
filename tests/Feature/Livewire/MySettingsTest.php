<?php

use App\Livewire\MySettings;
use App\Models\User;
use Livewire\Livewire;

it('silences and unsilences the signed-in user', function () {
    $alice = User::factory()->create();
    $until = now()->addDay()->startOfSecond();

    Livewire::actingAs($alice)
        ->test(MySettings::class)
        ->set('silenceUntil', $until->toDateTimeLocalString())
        ->set('silenceReason', 'On leave')
        ->call('silence');

    expect($alice->fresh()->silenced_until)->not->toBeNull();

    Livewire::actingAs($alice)
        ->test(MySettings::class)
        ->call('unsilence');

    expect($alice->fresh()->silenced_until)->toBeNull();
});

it('saves email preferences for the signed-in user', function () {
    $alice = User::factory()->create([
        'notification_email' => null,
        'sender_email' => null,
    ]);

    Livewire::actingAs($alice)
        ->test(MySettings::class)
        ->set('notificationEmail', 'alice-alerts@example.ac.uk')
        ->set('senderEmail', 'cronmon-noreply@example.ac.uk')
        ->call('saveEmails')
        ->assertHasNoErrors();

    $alice->refresh();
    expect($alice->notification_email)->toBe('alice-alerts@example.ac.uk')
        ->and($alice->sender_email)->toBe('cronmon-noreply@example.ac.uk');
});
