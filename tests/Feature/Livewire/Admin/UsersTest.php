<?php

use App\Livewire\Admin\Users;
use App\Models\User;
use Livewire\Livewire;

it('renders the users page through the HTTP layer for an admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->count(3)->create();

    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
});

it('toggles the admin flag on a user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['is_admin' => false]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('toggleAdmin', $target->id);

    expect($target->fresh()->is_admin)->toBeTrue();
});

it('does not let an admin demote themselves via toggleAdmin', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('toggleAdmin', $admin->id);

    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('does not render an admin toggle for the current user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $other = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->assertDontSeeHtml("toggleAdmin({$admin->id})")
        ->assertSeeHtml("toggleAdmin({$other->id})");
});

it('does not render a staff toggle anywhere on the page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->count(2)->create();

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->assertDontSeeHtml('toggleStaff');
});
