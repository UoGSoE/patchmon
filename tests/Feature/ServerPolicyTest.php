<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;

it('lets a team member view a server owned by their team', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create();

    expect($alice->can('view', $server))->toBeTrue();
});

it('lets a team member update a server owned by their team', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);
    $server = Server::factory()->forTeam($team)->create();

    expect($alice->can('update', $server))->toBeTrue();
});

it('lets an admin update any server, even one in a team they are not a member of', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    $server = Server::factory()->forTeam($team)->create();

    expect($admin->can('update', $server))->toBeTrue();
});

it('lets any authenticated user create a server', function () {
    $user = User::factory()->create();

    expect($user->can('create', Server::class))->toBeTrue();
});

it('refuses a non-team-member trying to view a team-owned server', function () {
    $stranger = User::factory()->create();
    $team = Team::factory()->create();
    $server = Server::factory()->forTeam($team)->create();

    expect($stranger->can('view', $server))->toBeFalse();
});

it('refuses a non-team-member trying to delete a team-owned server', function () {
    $stranger = User::factory()->create();
    $team = Team::factory()->create();
    $server = Server::factory()->forTeam($team)->create();

    expect($stranger->can('delete', $server))->toBeFalse();
});
