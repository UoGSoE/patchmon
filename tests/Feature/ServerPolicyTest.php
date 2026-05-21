<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;

it('lets the owning user view their own job', function () {
    $owner = User::factory()->create();
    $server = Server::factory()->forUser($owner)->create();

    expect($owner->can('view', $server))->toBeTrue();
});

it('lets a team member update a job owned by their team', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create();

    expect($alice->can('update', $server))->toBeTrue();
});

it('refuses a non-team-member trying to delete a team-owned job', function () {
    $stranger = User::factory()->create();
    $team = Team::factory()->create();
    $server = Server::factory()->forTeam($team)->create();

    expect($stranger->can('delete', $server))->toBeFalse();
});

it('lets an admin update any job', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $someoneElse = User::factory()->create();
    $server = Server::factory()->forUser($someoneElse)->create();

    expect($admin->can('update', $server))->toBeTrue();
});

it('lets any authenticated user create a job', function () {
    $user = User::factory()->create();

    expect($user->can('create', Server::class))->toBeTrue();
});

it('refuses an unrelated user trying to view a personal job they do not own', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $server = Server::factory()->forUser($owner)->create();

    expect($stranger->can('view', $server))->toBeFalse();
});
