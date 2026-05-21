<?php

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Livewire\HomePage;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('creates a server via the new-server flyout', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($user);

    Livewire::actingAs($user)
        ->test(HomePage::class)
        ->call('openCreate')
        ->set('form.name', 'Net Services nightly')
        ->set('form.os_type', OsType::Linux->value)
        ->set('form.interval_months', 1)
        ->set('form.grace_value', 7)
        ->set('form.grace_units', GraceUnit::Days->value)
        ->set('form.team_id', $team->id)
        ->call('save')
        ->assertHasNoErrors();

    $server = Server::firstWhere('name', 'Net Services nightly');

    expect($server)->not->toBeNull()
        ->and($server->team_id)->toBe($team->id)
        ->and($server->os_type)->toBe(OsType::Linux)
        ->and($server->interval_months)->toBe(1)
        ->and($server->created_by_user_id)->toBe($user->id);
});

it('shows validation errors when name is missing', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($user);

    Livewire::actingAs($user)
        ->test(HomePage::class)
        ->call('openCreate')
        ->set('form.name', '')
        ->set('form.os_type', OsType::Linux->value)
        ->set('form.team_id', $team->id)
        ->set('form.grace_value', 7)
        ->set('form.grace_units', GraceUnit::Days->value)
        ->call('save')
        ->assertHasErrors(['form.name']);

    expect(Server::count())->toBe(0);
});

it('rejects a team_id the user is not a member of', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();

    Livewire::actingAs($user)
        ->test(HomePage::class)
        ->call('openCreate')
        ->set('form.name', 'Sneaky')
        ->set('form.os_type', OsType::Linux->value)
        ->set('form.team_id', $otherTeam->id)
        ->set('form.grace_value', 7)
        ->set('form.grace_units', GraceUnit::Days->value)
        ->call('save')
        ->assertHasErrors(['form.team_id']);

    expect(Server::count())->toBe(0);
});

it('rejects creating a server with no team set', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(HomePage::class)
        ->call('openCreate')
        ->set('form.name', 'Orphan')
        ->set('form.os_type', OsType::Linux->value)
        ->set('form.grace_value', 7)
        ->set('form.grace_units', GraceUnit::Days->value)
        ->call('save')
        ->assertHasErrors(['form.team_id']);

    expect(Server::count())->toBe(0);
});

it('persists the location field when set on the new-server form', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($user);

    Livewire::actingAs($user)
        ->test(HomePage::class)
        ->call('openCreate')
        ->set('form.name', 'Located server')
        ->set('form.location', 'Rankine')
        ->set('form.os_type', OsType::Linux->value)
        ->set('form.interval_months', 1)
        ->set('form.grace_value', 7)
        ->set('form.grace_units', GraceUnit::Days->value)
        ->set('form.team_id', $team->id)
        ->call('save')
        ->assertHasNoErrors();

    $server = Server::firstWhere('name', 'Located server');
    expect($server)->not->toBeNull()
        ->and($server->location)->toBe('Rankine');
});
