<?php

use App\Livewire\Admin\Users;
use App\Models\User;
use Livewire\Livewire;

it('toggles the admin flag on a user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['is_admin' => false]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('toggleAdmin', $target->id);

    expect($target->fresh()->is_admin)->toBeTrue();
});
