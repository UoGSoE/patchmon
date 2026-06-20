<?php

use App\Models\User;

it('scopes to only the users flagged as oversight admins', function () {
    $oversightAdmin = User::factory()->oversightAdmin()->create();
    $regularUser = User::factory()->create();

    $result = User::oversightAdmins()->get();

    expect($result->pluck('id')->all())->toBe([$oversightAdmin->id]);
});

it('returns no oversight admins when nobody is flagged', function () {
    User::factory()->count(3)->create();

    expect(User::oversightAdmins()->get())->toBeEmpty();
});
