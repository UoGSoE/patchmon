<?php

use App\Models\ActivityLog;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('logs creating a user via the API', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->postJson('/api/v1/admin/users', [
        'username' => 'kmc2y',
        'forenames' => 'Kit',
        'surname' => 'McKay',
        'email' => 'kit@example.test',
    ])->assertCreated();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->server_id)->toBeNull();
    expect($log->description)->toContain('Kit McKay');
    expect($log->source_ip)->not->toBeNull();
});

it('logs deleting a user via the API', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['forenames' => 'Gone', 'surname' => 'Soon']);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->deleteJson("/api/v1/admin/users/{$target->id}")->assertNoContent();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->description)->toContain('Gone Soon');
    expect($log->description)->toContain('Deleted');
});
