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

it('silenceUntil persists the cutoff and reason on the job', function () {
    $job = Job::factory()->create();
    $until = now()->addDay()->startOfSecond();

    $job->silenceUntil($until, 'Power works in the data centre');

    $job->refresh();
    expect($job->silenced_until->equalTo($until))->toBeTrue()
        ->and($job->silence_reason)->toBe('Power works in the data centre')
        ->and($job->isCurrentlySilenced())->toBeTrue();
});

it('unsilence clears the cutoff and reason on the job', function () {
    $job = Job::factory()->silenced()->create();

    $job->unsilence();

    $job->refresh();
    expect($job->silenced_until)->toBeNull()
        ->and($job->silence_reason)->toBeNull()
        ->and($job->isCurrentlySilenced())->toBeFalse();
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
