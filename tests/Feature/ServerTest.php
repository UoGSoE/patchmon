<?php

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;

it('can create a user-owned job with sensible defaults', function () {
    $user = User::factory()->create();
    $server = Server::factory()->forUser($user)->create();

    expect($server->user_id)->toBe($user->id)
        ->and($server->team_id)->toBeNull()
        ->and($server->created_by_user_id)->not->toBeNull()
        ->and($server->name)->not->toBeEmpty();
});

it('auto-generates a unique patch_token when one is not provided', function () {
    $jobA = Server::factory()->create();
    $jobB = Server::factory()->create();

    expect($jobA->patch_token)->toBeString()->not->toBeEmpty()
        ->and($jobB->patch_token)->toBeString()->not->toBeEmpty()
        ->and($jobA->patch_token)->not->toBe($jobB->patch_token);
});

it('silenceUntil persists the cutoff and reason on the job', function () {
    $server = Server::factory()->create();
    $until = now()->addDay()->startOfSecond();

    $server->silenceUntil($until, 'Power works in the data centre');

    $server->refresh();
    expect($server->silenced_until->equalTo($until))->toBeTrue()
        ->and($server->silence_reason)->toBe('Power works in the data centre')
        ->and($server->isCurrentlySilenced())->toBeTrue();
});

it('unsilence clears the cutoff and reason on the job', function () {
    $server = Server::factory()->silenced()->create();

    $server->unsilence();

    $server->refresh();
    expect($server->silenced_until)->toBeNull()
        ->and($server->silence_reason)->toBeNull()
        ->and($server->isCurrentlySilenced())->toBeFalse();
});

it('casts enums and exposes team / createdBy relationships', function () {
    $team = Team::factory()->create();
    $creator = User::factory()->create();

    $server = Server::factory()->forTeam($team, $creator)->create();

    expect($server->team)->toBeInstanceOf(Team::class)
        ->and($server->team->is($team))->toBeTrue()
        ->and($server->createdBy)->toBeInstanceOf(User::class)
        ->and($server->createdBy->is($creator))->toBeTrue()
        ->and($server->schedule_interval)->toBe(ScheduleInterval::Daily)
        ->and($server->grace_units)->toBe(GraceUnit::Hours);
});

it('persists an optional location string on a job', function () {
    $server = Server::factory()->create(['location' => 'Rankine']);

    expect($server->fresh()->location)->toBe('Rankine');
});
