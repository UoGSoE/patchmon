<?php

use App\Livewire\Admin\ApiTokens;
use App\Models\User;
use Livewire\Livewire;

it('renders /admin/api-tokens for an admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get(route('admin.api-tokens.index'))->assertOk();
});

it('refuses /admin/api-tokens for a non-admin', function () {
    $alice = User::factory()->create(['is_admin' => false]);

    $this->actingAs($alice)->get(route('admin.api-tokens.index'))->assertStatus(403);
});

it('revokes another users token from the admin page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $alice = User::factory()->create();
    $token = $alice->createToken('alice-laptop')->accessToken;

    Livewire::actingAs($admin)
        ->test(ApiTokens::class)
        ->call('confirmRevokeToken', $token->id)
        ->call('revokeToken')
        ->assertHasNoErrors();

    expect($alice->tokens()->count())->toBe(0);
});

it('lists every users tokens for an admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $alice = User::factory()->create(['forenames' => 'Alice', 'surname' => 'Anderson']);
    $bob = User::factory()->create(['forenames' => 'Bob', 'surname' => 'Brown']);
    $alice->createToken('alice-laptop');
    $bob->createToken('bob-laptop');

    Livewire::actingAs($admin)
        ->test(ApiTokens::class)
        ->assertSee('alice-laptop')
        ->assertSee('bob-laptop')
        ->assertSee('Alice Anderson')
        ->assertSee('Bob Brown');
});
