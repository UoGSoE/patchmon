<?php

use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('removes a user from a team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $team->users()->attach([$alice->id, $bob->id]);
    $otherTeam->users()->attach($alice);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->deleteJson("/api/v1/admin/teams/{$team->id}/members/{$alice->id}")
        ->assertNoContent();

    expect($team->users()->pluck('users.id')->all())
        ->not->toContain($alice->id)
        ->toContain($bob->id);
    expect($otherTeam->users()->pluck('users.id')->all())->toContain($alice->id);
});

it('adds a user to a team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    $alice = User::factory()->create();
    Sanctum::actingAs($admin, ['admin:write']);

    $this->postJson("/api/v1/admin/teams/{$team->id}/members", ['user_id' => $alice->id])
        ->assertCreated();

    expect($team->users()->pluck('users.id')->all())->toContain($alice->id);
});
