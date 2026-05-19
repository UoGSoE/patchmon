<?php

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Models\Job;
use App\Models\Team;
use App\Models\User;

it('can create a user-owned job with sensible defaults', function () {
    $user = User::factory()->create();
    $job = Job::factory()->forUser($user)->create();

    expect($job->user_id)->toBe($user->id)
        ->and($job->team_id)->toBeNull()
        ->and($job->created_by_user_id)->not->toBeNull()
        ->and($job->name)->not->toBeEmpty();
});

it('auto-generates a unique check_in_token when one is not provided', function () {
    $jobA = Job::factory()->create();
    $jobB = Job::factory()->create();

    expect($jobA->check_in_token)->toBeString()->not->toBeEmpty()
        ->and($jobB->check_in_token)->toBeString()->not->toBeEmpty()
        ->and($jobA->check_in_token)->not->toBe($jobB->check_in_token);
});

it('inherits requires_bearer_token from the owning user when not explicitly set', function () {
    $strictUser = User::factory()->create(['check_ins_require_token' => true]);
    $relaxedUser = User::factory()->create(['check_ins_require_token' => false]);

    $strictJob = Job::factory()->forUser($strictUser)->create();
    $relaxedJob = Job::factory()->forUser($relaxedUser)->create();

    expect($strictJob->requires_bearer_token)->toBeTrue()
        ->and($relaxedJob->requires_bearer_token)->toBeFalse();
});

it('inherits requires_bearer_token from the owning team when not explicitly set', function () {
    $strictTeam = Team::factory()->create(['check_ins_require_token' => true]);
    $strictJob = Job::factory()->forTeam($strictTeam)->create();

    expect($strictJob->requires_bearer_token)->toBeTrue();
});

it('keeps an explicitly-set requires_bearer_token rather than inheriting', function () {
    $strictTeam = Team::factory()->create(['check_ins_require_token' => true]);

    $job = Job::factory()->forTeam($strictTeam)->create([
        'requires_bearer_token' => false,
    ]);

    expect($job->requires_bearer_token)->toBeFalse();
});

it('casts enums and exposes team / createdBy relationships', function () {
    $team = Team::factory()->create();
    $creator = User::factory()->create();

    $job = Job::factory()->forTeam($team, $creator)->create();

    expect($job->team)->toBeInstanceOf(Team::class)
        ->and($job->team->is($team))->toBeTrue()
        ->and($job->createdBy)->toBeInstanceOf(User::class)
        ->and($job->createdBy->is($creator))->toBeTrue()
        ->and($job->schedule_interval)->toBe(ScheduleInterval::Daily)
        ->and($job->grace_units)->toBe(GraceUnit::Hours);
});
