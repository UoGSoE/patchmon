<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;

it('resolves notification email to the job override when set', function () {
    $user = User::factory()->create(['notification_email' => 'user-default@example.com']);
    $server = Server::factory()->forUser($user)->create([
        'notification_email' => 'just-this-job@example.com',
    ]);

    expect($server->resolveNotificationEmail())->toBe('just-this-job@example.com');
});

it('resolves notification email to the user email when the user has no notification_email and the job has no override', function () {
    $user = User::factory()->create([
        'email' => 'alice@example.com',
        'notification_email' => null,
    ]);
    $server = Server::factory()->forUser($user)->create(['notification_email' => null]);

    expect($server->resolveNotificationEmail())->toBe('alice@example.com');
});

it('uses the user notification_email in preference to the user email when there is no job override', function () {
    $user = User::factory()->create([
        'email' => 'alice@example.com',
        'notification_email' => 'alice-alerts@example.com',
    ]);
    $server = Server::factory()->forUser($user)->create(['notification_email' => null]);

    expect($server->resolveNotificationEmail())->toBe('alice-alerts@example.com');
});

it('uses the team notification_email for team-owned jobs without an override', function () {
    $team = Team::factory()->create(['notification_email' => 'netservices@example.ac.uk']);
    $server = Server::factory()->forTeam($team)->create(['notification_email' => null]);

    expect($server->resolveNotificationEmail())->toBe('netservices@example.ac.uk');
});

it('resolves sender email to the job override when set', function () {
    $user = User::factory()->create(['sender_email' => 'user-default@example.com']);
    $server = Server::factory()->forUser($user)->create([
        'sender_email' => 'just-this-job@example.com',
    ]);

    expect($server->resolveSenderEmail())->toBe('just-this-job@example.com');
});

it('falls back to the team sender_email for team-owned jobs and returns null when nothing is set', function () {
    $teamWithSender = Team::factory()->create(['sender_email' => 'noreply-net@example.ac.uk']);
    $teamWithoutSender = Team::factory()->create(['sender_email' => null]);

    $jobWithTeamSender = Server::factory()->forTeam($teamWithSender)->create(['sender_email' => null]);
    $jobWithNoSender = Server::factory()->forTeam($teamWithoutSender)->create(['sender_email' => null]);

    expect($jobWithTeamSender->resolveSenderEmail())->toBe('noreply-net@example.ac.uk')
        ->and($jobWithNoSender->resolveSenderEmail())->toBeNull();
});

it('considers a job silenced when its own silenced_until is in the future', function () {
    $server = Server::factory()->silenced()->create();

    expect($server->isCurrentlySilenced())->toBeTrue();
});

it('does not consider a job silenced when silenced_until is in the past', function () {
    $server = Server::factory()->create(['silenced_until' => now()->subHour()]);

    expect($server->isCurrentlySilenced())->toBeFalse();
});

it('considers a team-owned job silenced when its team is silenced', function () {
    $team = Team::factory()->silenced()->create();
    $server = Server::factory()->forTeam($team)->create(['silenced_until' => null]);

    expect($server->isCurrentlySilenced())->toBeTrue();
});

it('considers a user-owned job silenced when its user is silenced', function () {
    $user = User::factory()->silenced()->create();
    $server = Server::factory()->forUser($user)->create(['silenced_until' => null]);

    expect($server->isCurrentlySilenced())->toBeTrue();
});
