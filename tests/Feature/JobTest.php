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
