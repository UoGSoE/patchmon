<?php

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Livewire\CreateJob;
use App\Models\Job;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('shows the create-job form to an authenticated user', function () {
    $alice = User::factory()->create();

    $this->actingAs($alice)->get(route('jobs.create'))->assertOk();
});

it('redirects unauthenticated visitors away from the create-job page', function () {
    $this->get(route('jobs.create'))->assertRedirect();
});

it('rejects a submission with missing required fields and creates no job', function () {
    $alice = User::factory()->create();

    Livewire::actingAs($alice)
        ->test(CreateJob::class)
        ->set('form.name', '')
        ->set('form.schedule_type', 'interval')
        ->set('form.schedule_interval', '')
        ->call('save')
        ->assertHasErrors(['form.name', 'form.schedule_interval']);

    expect(Job::count())->toBe(0);
});

it('creates a team-owned job when the user picks a team they belong to', function () {
    $alice = User::factory()->create();
    $team = Team::factory()->create();
    $alice->teams()->attach($team);

    Livewire::actingAs($alice)
        ->test(CreateJob::class)
        ->set('form.name', 'Team DNS export')
        ->set('form.schedule_type', 'interval')
        ->set('form.schedule_interval', ScheduleInterval::Daily->value)
        ->set('form.schedule_frequency', 1)
        ->set('form.grace_value', 30)
        ->set('form.grace_units', GraceUnit::Minutes->value)
        ->set('form.ownership_type', 'team')
        ->set('form.team_id', $team->id)
        ->call('save')
        ->assertHasNoErrors();

    $job = Job::firstWhere('name', 'Team DNS export');
    expect($job->team_id)->toBe($team->id)
        ->and($job->user_id)->toBeNull()
        ->and($job->created_by_user_id)->toBe($alice->id);
});

it('rejects a team-owned job when the user is not a member of the chosen team', function () {
    $alice = User::factory()->create();
    $otherTeam = Team::factory()->create();

    Livewire::actingAs($alice)
        ->test(CreateJob::class)
        ->set('form.name', 'Sneaky cross-team job')
        ->set('form.schedule_type', 'interval')
        ->set('form.schedule_interval', ScheduleInterval::Daily->value)
        ->set('form.schedule_frequency', 1)
        ->set('form.grace_value', 30)
        ->set('form.grace_units', GraceUnit::Minutes->value)
        ->set('form.ownership_type', 'team')
        ->set('form.team_id', $otherTeam->id)
        ->call('save')
        ->assertHasErrors('form.team_id');

    expect(Job::count())->toBe(0);
});

it('creates a cron-scheduled job and clears any interval fields', function () {
    $alice = User::factory()->create();

    Livewire::actingAs($alice)
        ->test(CreateJob::class)
        ->set('form.name', 'Top of every hour')
        ->set('form.schedule_type', 'cron')
        ->set('form.cron_expression', '0 * * * *')
        ->set('form.grace_value', 5)
        ->set('form.grace_units', GraceUnit::Minutes->value)
        ->set('form.ownership_type', 'mine')
        ->call('save')
        ->assertHasNoErrors();

    $job = Job::firstWhere('name', 'Top of every hour');
    expect($job->cron_expression)->toBe('0 * * * *')
        ->and($job->schedule_interval)->toBeNull();
});

it('creates a personal interval job and redirects to home with a toast', function () {
    $alice = User::factory()->create();

    Livewire::actingAs($alice)
        ->test(CreateJob::class)
        ->set('form.name', 'Nightly backup')
        ->set('form.schedule_type', 'interval')
        ->set('form.schedule_interval', ScheduleInterval::Daily->value)
        ->set('form.schedule_frequency', 1)
        ->set('form.grace_value', 30)
        ->set('form.grace_units', GraceUnit::Minutes->value)
        ->set('form.ownership_type', 'mine')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $job = Job::firstWhere('name', 'Nightly backup');
    expect($job)->not->toBeNull()
        ->and($job->user_id)->toBe($alice->id)
        ->and($job->team_id)->toBeNull()
        ->and($job->created_by_user_id)->toBe($alice->id)
        ->and($job->schedule_interval)->toBe(ScheduleInterval::Daily)
        ->and($job->schedule_frequency)->toBe(1)
        ->and($job->grace_value)->toBe(30)
        ->and($job->grace_units)->toBe(GraceUnit::Minutes)
        ->and($job->cron_expression)->toBeNull();
});
