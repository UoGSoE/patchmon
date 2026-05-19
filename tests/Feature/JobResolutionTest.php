<?php

use App\Models\Job;
use App\Models\Team;
use App\Models\User;

it('resolves notification email to the job override when set', function () {
    $user = User::factory()->create(['notification_email' => 'user-default@example.com']);
    $job = Job::factory()->forUser($user)->create([
        'notification_email' => 'just-this-job@example.com',
    ]);

    expect($job->resolveNotificationEmail())->toBe('just-this-job@example.com');
});

it('resolves notification email to the user email when the user has no notification_email and the job has no override', function () {
    $user = User::factory()->create([
        'email' => 'alice@example.com',
        'notification_email' => null,
    ]);
    $job = Job::factory()->forUser($user)->create(['notification_email' => null]);

    expect($job->resolveNotificationEmail())->toBe('alice@example.com');
});

it('uses the user notification_email in preference to the user email when there is no job override', function () {
    $user = User::factory()->create([
        'email' => 'alice@example.com',
        'notification_email' => 'alice-alerts@example.com',
    ]);
    $job = Job::factory()->forUser($user)->create(['notification_email' => null]);

    expect($job->resolveNotificationEmail())->toBe('alice-alerts@example.com');
});

it('uses the team notification_email for team-owned jobs without an override', function () {
    $team = Team::factory()->create(['notification_email' => 'netservices@example.ac.uk']);
    $job = Job::factory()->forTeam($team)->create(['notification_email' => null]);

    expect($job->resolveNotificationEmail())->toBe('netservices@example.ac.uk');
});

it('resolves sender email to the job override when set', function () {
    $user = User::factory()->create(['sender_email' => 'user-default@example.com']);
    $job = Job::factory()->forUser($user)->create([
        'sender_email' => 'just-this-job@example.com',
    ]);

    expect($job->resolveSenderEmail())->toBe('just-this-job@example.com');
});

it('falls back to the team sender_email for team-owned jobs and returns null when nothing is set', function () {
    $teamWithSender = Team::factory()->create(['sender_email' => 'noreply-net@example.ac.uk']);
    $teamWithoutSender = Team::factory()->create(['sender_email' => null]);

    $jobWithTeamSender = Job::factory()->forTeam($teamWithSender)->create(['sender_email' => null]);
    $jobWithNoSender = Job::factory()->forTeam($teamWithoutSender)->create(['sender_email' => null]);

    expect($jobWithTeamSender->resolveSenderEmail())->toBe('noreply-net@example.ac.uk')
        ->and($jobWithNoSender->resolveSenderEmail())->toBeNull();
});

it('considers a job silenced when its own silenced_until is in the future', function () {
    $job = Job::factory()->silenced()->create();

    expect($job->isCurrentlySilenced())->toBeTrue();
});

it('does not consider a job silenced when silenced_until is in the past', function () {
    $job = Job::factory()->create(['silenced_until' => now()->subHour()]);

    expect($job->isCurrentlySilenced())->toBeFalse();
});

it('considers a team-owned job silenced when its team is silenced', function () {
    $team = Team::factory()->silenced()->create();
    $job = Job::factory()->forTeam($team)->create(['silenced_until' => null]);

    expect($job->isCurrentlySilenced())->toBeTrue();
});

it('considers a user-owned job silenced when its user is silenced', function () {
    $user = User::factory()->silenced()->create();
    $job = Job::factory()->forUser($user)->create(['silenced_until' => null]);

    expect($job->isCurrentlySilenced())->toBeTrue();
});
