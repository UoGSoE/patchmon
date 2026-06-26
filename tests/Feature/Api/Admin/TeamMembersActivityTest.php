<?php

use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('logs adding a team member via the API', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['name' => 'Ops']);
    $member = User::factory()->create(['forenames' => 'Sam', 'surname' => 'Adams']);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->postJson("/api/v1/admin/teams/{$team->id}/members", ['user_id' => $member->id])
        ->assertCreated();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->description)->toContain('Sam Adams');
    expect($log->description)->toContain('Ops');
});

it('logs removing a team member via the API', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['name' => 'Ops']);
    $member = User::factory()->create(['forenames' => 'Sam', 'surname' => 'Adams']);
    $team->users()->attach($member);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->deleteJson("/api/v1/admin/teams/{$team->id}/members/{$member->id}")
        ->assertNoContent();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->description)->toContain('Sam Adams');
    expect($log->description)->toContain('Removed');
});
