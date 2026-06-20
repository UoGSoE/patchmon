<?php

use App\Models\User;

it('serves record_patched.sh to a logged-in user with the server url baked in and no sentinel left', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/scripts/record_patched.sh');

    $response->assertOk()->assertDownload('record_patched.sh');

    expect($response->streamedContent())
        ->toContain(rtrim(config('app.url'), '/'))
        ->not->toContain('__PATCHMON_URL__');
});

it('does not serve the script to a guest', function () {
    $this->get('/scripts/record_patched.sh')->assertRedirect(route('login'));
});
