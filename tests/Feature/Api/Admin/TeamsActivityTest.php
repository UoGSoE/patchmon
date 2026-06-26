<?php

use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('logs creating a team via the API', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->postJson('/api/v1/admin/teams', [
        'name' => 'Platform',
        'notification_email' => 'platform-alerts@example.test',
    ])->assertCreated();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->server_id)->toBeNull();
    expect($log->description)->toContain('Platform');
    expect($log->source_ip)->not->toBeNull();
});

it('logs deleting a team via the API, keeping the name in the description', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['name' => 'Retired']);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->deleteJson("/api/v1/admin/teams/{$team->id}")->assertNoContent();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->description)->toContain('Retired');
    expect($log->description)->toContain('Deleted');
});
