<?php

use App\Models\User;

it('redirects guests away from /api/help', function () {
    $this->get(route('api.help'))->assertRedirect(route('login'));
});

it('renders /api/help for an authenticated user', function () {
    $alice = User::factory()->create();

    $this->actingAs($alice)
        ->get(route('api.help'))
        ->assertOk()
        ->assertSee('PATCHMON_API_TOKEN')
        ->assertSee('curl');
});
