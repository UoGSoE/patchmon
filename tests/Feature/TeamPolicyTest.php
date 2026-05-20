<?php

use App\Models\Team;
use App\Models\User;

it('refuses a non-member non-admin from viewing a team', function () {
    $stranger = User::factory()->create(['is_admin' => false]);
    $team = Team::factory()->create();

    expect($stranger->can('view', $team))->toBeFalse();
});

it('only lets admins create new teams', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $regular = User::factory()->create(['is_admin' => false]);

    expect($admin->can('create', Team::class))->toBeTrue()
        ->and($regular->can('create', Team::class))->toBeFalse();
});

it('lets a team member view their team', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    expect($alice->can('view', $team))->toBeTrue();
});

it('only lets admins update or delete a team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $member = User::factory()->create(['is_admin' => false]);
    $team = Team::factory()->create();
    $member->teams()->attach($team);

    expect($admin->can('update', $team))->toBeTrue()
        ->and($admin->can('delete', $team))->toBeTrue()
        ->and($member->can('update', $team))->toBeFalse()
        ->and($member->can('delete', $team))->toBeFalse();
});
