<?php

use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('refuses /api/v1/admin/teams for a non-admin', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    Sanctum::actingAs($alice, ['admin:read']);

    $this->getJson('/api/v1/admin/teams')->assertStatus(403);
});

it('refuses /api/v1/admin/teams without the admin:read ability', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin, ['servers:read']);

    $this->getJson('/api/v1/admin/teams')->assertStatus(403);
});

it('lists every team for an admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Team::factory()->create(['name' => 'Alpha']);
    Team::factory()->create(['name' => 'Bravo']);

    Sanctum::actingAs($admin, ['admin:read']);

    $response = $this->getJson('/api/v1/admin/teams')->assertOk();
    $names = collect($response->json('teams'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['Alpha', 'Bravo']);
});
