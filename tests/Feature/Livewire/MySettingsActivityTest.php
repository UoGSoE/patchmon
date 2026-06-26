<?php

use App\Livewire\MySettings;
use App\Models\ActivityLog;
use App\Models\User;
use Livewire\Livewire;

it('logs creating a personal API token', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(MySettings::class)
        ->set('tokenName', 'laptop')
        ->set('tokenAbilities', ['servers:read'])
        ->call('createToken')
        ->assertHasNoErrors();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($user->id);
    expect($log->server_id)->toBeNull();
    expect($log->description)->toContain('laptop');
    expect($log->description)->toContain('Created');
});

it('logs revoking a personal API token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('laptop')->accessToken;

    Livewire::actingAs($user)
        ->test(MySettings::class)
        ->call('confirmRevokeToken', $token->id)
        ->call('revokeToken');

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($user->id);
    expect($log->description)->toContain('laptop');
    expect($log->description)->toContain('Revoked');
});
