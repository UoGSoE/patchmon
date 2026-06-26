<?php

use App\Livewire\Admin\TeamDetail;
use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('logs adding a member to a team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['name' => 'Ops']);
    $newMember = User::factory()->create(['forenames' => 'Sam', 'surname' => 'Adams']);

    Livewire::actingAs($admin)
        ->test(TeamDetail::class, ['team' => $team])
        ->set('userToAddId', $newMember->id)
        ->call('addUser')
        ->assertHasNoErrors();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->server_id)->toBeNull();
    expect($log->description)->toContain('Sam Adams');
    expect($log->description)->toContain('Ops');
});

it('logs removing a member from a team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['name' => 'Ops']);
    $member = User::factory()->create(['forenames' => 'Sam', 'surname' => 'Adams']);
    $team->users()->attach($member);

    Livewire::actingAs($admin)
        ->test(TeamDetail::class, ['team' => $team])
        ->call('removeUser', $member->id);

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->description)->toContain('Sam Adams');
    expect($log->description)->toContain('Removed');
});
