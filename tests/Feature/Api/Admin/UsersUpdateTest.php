<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('refuses to demote the signed-in admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->patchJson("/api/v1/admin/users/{$admin->id}", ['is_admin' => false])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_admin']);

    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('updates a user including promoting to admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['surname' => 'Old', 'is_admin' => false]);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->patchJson("/api/v1/admin/users/{$target->id}", [
        'surname' => 'New',
        'is_admin' => true,
    ])->assertOk();

    $fresh = $target->fresh();
    expect($fresh->surname)->toBe('New')
        ->and($fresh->is_admin)->toBeTrue();
});
