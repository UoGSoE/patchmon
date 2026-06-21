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

it('offers the record_patched.sh helper script download on /api/help', function () {
    $alice = User::factory()->create();

    $this->actingAs($alice)
        ->get(route('api.help'))
        ->assertOk()
        ->assertSee('First-run helper script')
        ->assertSee(route('scripts.record-patch'))
        ->assertSee(route('scripts.record-patch-ps'));
});
