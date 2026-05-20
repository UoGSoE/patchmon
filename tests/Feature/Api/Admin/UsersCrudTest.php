<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates a user via the admin API', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->postJson('/api/v1/admin/users', [
        'username' => 'kmc2y',
        'forenames' => 'Kit',
        'surname' => 'McAuthor',
        'email' => 'kit@example.test',
    ])->assertCreated();

    $created = User::firstWhere('username', 'kmc2y');
    expect($created)->not->toBeNull()
        ->and($created->is_staff)->toBeTrue()
        ->and($created->is_admin)->toBeFalse();
});

it('rejects a non-GUID username via the admin API', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->postJson('/api/v1/admin/users', [
        'username' => '1234567z',
        'forenames' => 'Kit',
        'surname' => 'McAuthor',
        'email' => 'kit@example.test',
    ])->assertStatus(422);

    expect(User::where('email', 'kit@example.test')->count())->toBe(0);
});

it('shows a single user via the admin API', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['username' => 'jed1y']);
    Sanctum::actingAs($admin, ['admin:read']);

    $this->getJson("/api/v1/admin/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('user.username', 'jed1y');
});

it('lists every user for an admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->count(2)->create();
    Sanctum::actingAs($admin, ['admin:read']);

    $response = $this->getJson('/api/v1/admin/users')->assertOk();

    expect($response->json('users'))->toHaveCount(3);
});
