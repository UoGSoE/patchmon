<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('refuses /api/v1/me without a valid token', function () {
    $this->getJson('/api/v1/me')->assertStatus(401);
});

it('returns the authenticated user via /api/v1/me', function () {
    $user = User::factory()->create([
        'username' => 'kmc2y',
        'forenames' => 'Kit',
        'surname' => 'McAuthor',
        'email' => 'kit@example.test',
        'is_admin' => false,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertExactJson([
            'user' => [
                'id' => $user->id,
                'username' => 'kmc2y',
                'full_name' => 'Kit McAuthor',
                'email' => 'kit@example.test',
                'is_admin' => false,
                'is_staff' => true,
            ],
        ]);
});
