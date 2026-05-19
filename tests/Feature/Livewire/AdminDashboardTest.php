<?php

use App\Models\User;

it('forbids non-admins from the admin dashboard', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
});

it('shows the admin dashboard to admin users', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
});
