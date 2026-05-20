<?php

use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('refuses /api/v1/teams without a valid token', function () {
    $this->getJson('/api/v1/teams')->assertStatus(401);
});

it('returns only the teams the authenticated user is a member of', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $mine = Team::factory()->create(['name' => 'Mine']);
    $someoneElses = Team::factory()->create(['name' => 'Someone Elses']);
    $alice->teams()->attach($mine);

    Sanctum::actingAs($alice);

    $response = $this->getJson('/api/v1/teams')->assertOk();

    $names = collect($response->json('teams'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['Mine']);
});

it('returns every team for an admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Team::factory()->create(['name' => 'Alpha']);
    Team::factory()->create(['name' => 'Bravo']);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/teams')->assertOk();

    $names = collect($response->json('teams'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['Alpha', 'Bravo']);
});
