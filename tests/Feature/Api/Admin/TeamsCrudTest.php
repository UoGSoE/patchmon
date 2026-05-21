<?php

use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('shows a team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['name' => 'Bob']);
    Sanctum::actingAs($admin, ['admin:read']);

    $this->getJson("/api/v1/admin/teams/{$team->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Bob');
});

it('updates a team via PATCH', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['name' => 'Old']);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->patchJson("/api/v1/admin/teams/{$team->id}", ['name' => 'New'])->assertOk();

    expect($team->fresh()->name)->toBe('New');
});

it('deletes a team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    $bystander = Team::factory()->create();
    Sanctum::actingAs($admin, ['admin:write']);

    $this->deleteJson("/api/v1/admin/teams/{$team->id}")->assertNoContent();

    expect(Team::find($team->id))->toBeNull()
        ->and(Team::find($bystander->id))->not->toBeNull();
});

it('creates a team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->postJson('/api/v1/admin/teams', [
        'name' => 'Platform',
        'notification_email' => 'platform-alerts@example.test',
    ])->assertCreated();

    expect(Team::where('name', 'Platform')->count())->toBe(1);
});
