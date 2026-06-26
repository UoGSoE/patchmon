<?php

use App\Livewire\Admin\ApiTokens;
use App\Models\ActivityLog;
use App\Models\User;
use Livewire\Livewire;

it('logs an admin revoking another users API token, naming the owner', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create(['forenames' => 'Bob', 'surname' => 'Brown']);
    $token = $owner->createToken('backup')->accessToken;

    Livewire::actingAs($admin)
        ->test(ApiTokens::class)
        ->call('confirmRevokeToken', $token->id)
        ->call('revokeToken');

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->server_id)->toBeNull();
    expect($log->description)->toContain('Bob Brown');
    expect($log->description)->toContain('backup');
});
