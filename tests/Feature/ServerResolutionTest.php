<?php

use App\Models\Server;
use App\Models\Team;

it('resolves notification email to the server override when set', function () {
    $team = Team::factory()->create(['notification_email' => 'team-default@example.com']);
    $server = Server::factory()->forTeam($team)->create([
        'notification_email' => 'just-this-server@example.com',
    ]);

    expect($server->resolveNotificationEmail())->toBe('just-this-server@example.com');
});

it('falls back to the team notification email when the server has no override', function () {
    $team = Team::factory()->create(['notification_email' => 'netservices@example.ac.uk']);
    $server = Server::factory()->forTeam($team)->create(['notification_email' => null]);

    expect($server->resolveNotificationEmail())->toBe('netservices@example.ac.uk');
});

it('resolves sender email to the server override when set', function () {
    $team = Team::factory()->create(['sender_email' => 'team-default@example.com']);
    $server = Server::factory()->forTeam($team)->create([
        'sender_email' => 'just-this-server@example.com',
    ]);

    expect($server->resolveSenderEmail())->toBe('just-this-server@example.com');
});

it('falls back to the team sender_email when the server has no override', function () {
    $team = Team::factory()->create(['sender_email' => 'noreply-net@example.ac.uk']);
    $server = Server::factory()->forTeam($team)->create(['sender_email' => null]);

    expect($server->resolveSenderEmail())->toBe('noreply-net@example.ac.uk');
});

it('returns null sender email when neither the server nor the team has one set', function () {
    $team = Team::factory()->create(['sender_email' => null]);
    $server = Server::factory()->forTeam($team)->create(['sender_email' => null]);

    expect($server->resolveSenderEmail())->toBeNull();
});

it('considers a server silenced when its own silenced_until is in the future', function () {
    $server = Server::factory()->silenced()->create();

    expect($server->isCurrentlySilenced())->toBeTrue();
});

it('does not consider a server silenced when silenced_until is in the past', function () {
    $server = Server::factory()->create(['silenced_until' => now()->subHour()]);

    expect($server->isCurrentlySilenced())->toBeFalse();
});

it('does not look at team-level silencing — only the server itself can be silenced', function () {
    $team = Team::factory()->create();
    $server = Server::factory()->forTeam($team)->create(['silenced_until' => null]);

    expect($server->isCurrentlySilenced())->toBeFalse();
});
