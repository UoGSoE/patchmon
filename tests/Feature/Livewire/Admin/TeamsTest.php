<?php

use App\Livewire\Admin\Teams;
use App\Models\Job;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('updates an existing team when opened for edit', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['name' => 'Old name']);

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('openEdit', $team->id)
        ->set('editing.name', 'Storage')
        ->call('save')
        ->assertHasNoErrors();

    expect($team->fresh()->name)->toBe('Storage');
});

it('deletes a team that owns no jobs', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->set('deletingId', $team->id)
        ->call('deleteEmpty');

    expect(Team::find($team->id))->toBeNull();
});

it('transfers a team\'s jobs to another team and deletes the original', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $doomed = Team::factory()->create(['name' => 'Doomed']);
    $target = Team::factory()->create(['name' => 'Target']);
    $bystander = Team::factory()->create(['name' => 'Bystander']);

    $doomedJobs = Job::factory()->count(2)->forTeam($doomed)->create();
    $bystanderJob = Job::factory()->forTeam($bystander)->create();

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->set('deletingId', $doomed->id)
        ->set('transferTargetTeamId', $target->id)
        ->call('transferToTeamAndDelete');

    expect(Team::find($doomed->id))->toBeNull();

    foreach ($doomedJobs as $job) {
        $fresh = $job->fresh();
        expect($fresh->team_id)->toBe($target->id)
            ->and($fresh->user_id)->toBeNull();
    }

    expect($bystanderJob->fresh()->team_id)->toBe($bystander->id);
});

it('transfers a team\'s jobs to a user and deletes the original', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $doomed = Team::factory()->create(['name' => 'Doomed']);
    $newOwner = User::factory()->create();

    $doomedJobs = Job::factory()->count(2)->forTeam($doomed)->create();

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->set('deletingId', $doomed->id)
        ->set('transferTargetUserId', $newOwner->id)
        ->call('transferToUserAndDelete');

    expect(Team::find($doomed->id))->toBeNull();

    foreach ($doomedJobs as $job) {
        $fresh = $job->fresh();
        expect($fresh->user_id)->toBe($newOwner->id)
            ->and($fresh->team_id)->toBeNull();
    }
});

it('deletes a team and its jobs when the typed confirmation matches the team name', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $doomed = Team::factory()->create(['name' => 'Doomed']);
    $jobs = Job::factory()->count(2)->forTeam($doomed)->create();

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->set('deletingId', $doomed->id)
        ->set('typedConfirmation', 'Doomed')
        ->call('deleteWithJobs');

    expect(Team::find($doomed->id))->toBeNull();
    foreach ($jobs as $job) {
        expect(Job::find($job->id))->toBeNull();
    }
});

it('does not delete a team when the typed confirmation does not match', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $doomed = Team::factory()->create(['name' => 'Doomed']);
    $job = Job::factory()->forTeam($doomed)->create();

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->set('deletingId', $doomed->id)
        ->set('typedConfirmation', 'Doomd')
        ->call('deleteWithJobs');

    expect(Team::find($doomed->id))->not->toBeNull()
        ->and(Job::find($job->id))->not->toBeNull();
});

it('lets an admin create a new team via the modal', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->set('editing.name', 'Network Services')
        ->set('editing.notification_email', 'netservices@example.ac.uk')
        ->call('save')
        ->assertHasNoErrors();

    expect(Team::firstWhere('name', 'Network Services'))->not->toBeNull();
});
