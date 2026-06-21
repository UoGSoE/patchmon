<?php

use App\Models\User;

it('forbids signed-in non-staff users from the web app', function () {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->get(route('home'))
        ->assertForbidden();
});

it('allows signed-in staff users into the web app', function () {
    $staff = User::factory()->create();

    $this->actingAs($staff)
        ->get(route('home'))
        ->assertOk();
});

it('redirects guests to the login screen', function () {
    $this->get(route('home'))
        ->assertRedirect(route('login'));
});
