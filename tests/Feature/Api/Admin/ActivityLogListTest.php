<?php

use App\Models\ActivityLog;
use App\Models\Server;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('refuses /api/v1/admin/activity for a non-admin', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    Sanctum::actingAs($alice, ['admin:read']);

    $this->getJson('/api/v1/admin/activity')->assertStatus(403);
});

it('lists activity log entries newest first for an admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    ActivityLog::factory()->create(['description' => 'Older thing', 'created_at' => now()->subDay()]);
    ActivityLog::factory()->create(['description' => 'Newer thing', 'created_at' => now()]);

    Sanctum::actingAs($admin, ['admin:read']);

    $response = $this->getJson('/api/v1/admin/activity')->assertOk();

    $entries = $response->json('activity.data');
    expect($entries)->toHaveCount(2);
    expect($entries[0]['description'])->toBe('Newer thing');
    expect($entries[1]['description'])->toBe('Older thing');
});

it('filters activity by server_id', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $server = Server::factory()->create();

    ActivityLog::factory()->forServer($server)->create(['description' => 'On this server']);
    ActivityLog::factory()->create(['description' => 'Unrelated']);

    Sanctum::actingAs($admin, ['admin:read']);

    $entries = $this->getJson('/api/v1/admin/activity?server_id='.$server->id)
        ->assertOk()
        ->json('activity.data');

    expect($entries)->toHaveCount(1);
    expect($entries[0]['description'])->toBe('On this server');
});
