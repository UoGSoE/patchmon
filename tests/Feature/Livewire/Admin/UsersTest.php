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
