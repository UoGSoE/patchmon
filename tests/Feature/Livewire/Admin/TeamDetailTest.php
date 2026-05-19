<?php

use App\Livewire\Admin\TeamDetail;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('removes a user from the team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    $member = User::factory()->create();
    $team->users()->attach($member);

    Livewire::actingAs($admin)
        ->test(TeamDetail::class, ['team' => $team])
        ->call('removeUser', $member->id);

    expect($team->users()->whereKey($member->id)->exists())->toBeFalse();
});

it('silences and unsilences the team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    $until = now()->addDay()->startOfSecond();

    Livewire::actingAs($admin)
        ->test(TeamDetail::class, ['team' => $team])
        ->set('silenceUntil', $until->toDateTimeLocalString())
        ->call('silence');

    expect($team->fresh()->silenced_until)->not->toBeNull();

    Livewire::actingAs($admin)
        ->test(TeamDetail::class, ['team' => $team->fresh()])
        ->call('unsilence');

    expect($team->fresh()->silenced_until)->toBeNull();
});

it('adds a user to the team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    $candidate = User::factory()->create(['email' => 'newbie@example.ac.uk']);

    Livewire::actingAs($admin)
        ->test(TeamDetail::class, ['team' => $team])
        ->set('userToAddId', $candidate->id)
        ->call('addUser');

    expect($team->users()->whereKey($candidate->id)->exists())->toBeTrue();
});

it('renders members and candidate names without errors', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    $member = User::factory()->create(['forenames' => 'Alex', 'surname' => 'Member']);
    $candidate = User::factory()->create(['forenames' => 'Casey', 'surname' => 'Candidate']);
    $team->users()->attach($member);

    $this->actingAs($admin)
        ->get(route('admin.teams.show', $team))
        ->assertOk()
        ->assertSee('Alex Member')
        ->assertSee('Casey Candidate');
});
