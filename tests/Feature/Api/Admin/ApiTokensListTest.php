<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('refuses /api/v1/admin/api-tokens for a non-admin', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    Sanctum::actingAs($alice, ['admin:read']);

    $this->getJson('/api/v1/admin/api-tokens')->assertStatus(403);
});

it('lists every token across every user with owner info', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $alice = User::factory()->create(['username' => 'ala1n', 'forenames' => 'Alice', 'surname' => 'Anderson', 'email' => 'alice@example.test']);
    $bob = User::factory()->create(['username' => 'bob1n']);
    $alice->createToken('alice-laptop', ['servers:read']);
    $bob->createToken('bob-backup', ['servers:read', 'servers:write']);

    Sanctum::actingAs($admin, ['admin:read']);

    $response = $this->getJson('/api/v1/admin/api-tokens')->assertOk();

    $tokens = collect($response->json('tokens'));
    expect($tokens)->toHaveCount(2);

    $aliceRow = $tokens->firstWhere('name', 'alice-laptop');
    expect($aliceRow['abilities'])->toEqual(['servers:read'])
        ->and($aliceRow['owner']['username'])->toBe('ala1n')
        ->and($aliceRow['owner']['full_name'])->toBe('Alice Anderson')
        ->and($aliceRow['owner']['email'])->toBe('alice@example.test');
});
