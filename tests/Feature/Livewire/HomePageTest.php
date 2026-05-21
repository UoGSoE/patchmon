<?php

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Livewire\HomePage;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('creates a personal job via the new-job flyout', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(HomePage::class)
        ->call('openCreate')
        ->set('form.name', 'Backup script')
        ->set('form.schedule_interval', ScheduleInterval::Daily->value)
        ->set('form.schedule_frequency', 1)
        ->set('form.grace_value', 15)
        ->set('form.grace_units', GraceUnit::Minutes->value)
        ->call('save')
        ->assertHasNoErrors();

    $server = Server::firstWhere('name', 'Backup script');

    expect($server)->not->toBeNull()
        ->and($server->user_id)->toBe($user->id)
        ->and($server->team_id)->toBeNull()
        ->and($server->schedule_interval)->toBe(ScheduleInterval::Daily)
        ->and($server->created_by_user_id)->toBe($user->id);
});

it('creates a team job via the new-job flyout when a team is selected', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($user);

    Livewire::actingAs($user)
        ->test(HomePage::class)
        ->call('openCreate')
        ->set('form.name', 'Net Services nightly')
        ->set('form.cron_expression', '0 2 * * *')
        ->set('form.grace_value', 30)
        ->set('form.grace_units', GraceUnit::Minutes->value)
        ->set('form.team_id', $team->id)
        ->call('save')
        ->assertHasNoErrors();

    $server = Server::firstWhere('name', 'Net Services nightly');

    expect($server)->not->toBeNull()
        ->and($server->team_id)->toBe($team->id)
        ->and($server->user_id)->toBeNull()
        ->and($server->cron_expression)->toBe('0 2 * * *');
});

it('shows validation errors when name is missing', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(HomePage::class)
        ->call('openCreate')
        ->set('form.name', '')
        ->set('form.schedule_interval', ScheduleInterval::Daily->value)
        ->set('form.grace_value', 5)
        ->set('form.grace_units', GraceUnit::Minutes->value)
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
        ->set('form.schedule_interval', ScheduleInterval::Daily->value)
        ->set('form.grace_value', 5)
        ->set('form.grace_units', GraceUnit::Minutes->value)
        ->set('form.team_id', $otherTeam->id)
        ->call('save')
        ->assertHasErrors(['form.team_id']);

    expect(Server::count())->toBe(0);
});

it('persists the location field when set on the new-job form', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(HomePage::class)
        ->call('openCreate')
        ->set('form.name', 'Located backup')
        ->set('form.location', 'Rankine')
        ->set('form.schedule_interval', ScheduleInterval::Daily->value)
        ->set('form.schedule_frequency', 1)
        ->set('form.grace_value', 15)
        ->set('form.grace_units', GraceUnit::Minutes->value)
        ->call('save')
        ->assertHasNoErrors();

    $server = Server::firstWhere('name', 'Located backup');
    expect($server)->not->toBeNull()
        ->and($server->location)->toBe('Rankine');
});
