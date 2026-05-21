<?php

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;

it('belongs to a team and has its creator tracked separately', function () {
    $team = Team::factory()->create();
    $creator = User::factory()->create();

    $server = Server::factory()->forTeam($team, $creator)->create();

    expect($server->team_id)->toBe($team->id)
        ->and($server->created_by_user_id)->toBe($creator->id)
        ->and($server->team->is($team))->toBeTrue()
        ->and($server->createdBy->is($creator))->toBeTrue();
});

it('auto-generates a unique patch_token when one is not provided', function () {
    $serverA = Server::factory()->create();
    $serverB = Server::factory()->create();

    expect($serverA->patch_token)->toBeString()->not->toBeEmpty()
        ->and($serverB->patch_token)->toBeString()->not->toBeEmpty()
        ->and($serverA->patch_token)->not->toBe($serverB->patch_token);
});

it('silenceUntil persists the cutoff and reason on the server', function () {
    $server = Server::factory()->create();
    $until = now()->addDay()->startOfSecond();

    $server->silenceUntil($until, 'Power works in the data centre');

    $server->refresh();
    expect($server->silenced_until->equalTo($until))->toBeTrue()
        ->and($server->silence_reason)->toBe('Power works in the data centre')
        ->and($server->isCurrentlySilenced())->toBeTrue();
});

it('unsilence clears the cutoff and reason on the server', function () {
    $server = Server::factory()->silenced()->create();

    $server->unsilence();

    $server->refresh();
    expect($server->silenced_until)->toBeNull()
        ->and($server->silence_reason)->toBeNull()
        ->and($server->isCurrentlySilenced())->toBeFalse();
});

it('casts os_type and grace_units to enums', function () {
    $server = Server::factory()->create();

    expect($server->os_type)->toBe(OsType::Linux)
        ->and($server->grace_units)->toBe(GraceUnit::Days);
});

it('persists an optional location string on a server', function () {
    $server = Server::factory()->create(['location' => 'Rankine']);

    expect($server->fresh()->location)->toBe('Rankine');
});

it('returns a friendly label for common patching intervals and falls back for unusual ones', function () {
    expect(Server::factory()->withInterval(1)->make()->intervalLabel())->toBe('Monthly')
        ->and(Server::factory()->withInterval(3)->make()->intervalLabel())->toBe('Quarterly')
        ->and(Server::factory()->withInterval(6)->make()->intervalLabel())->toBe('Twice-yearly')
        ->and(Server::factory()->withInterval(12)->make()->intervalLabel())->toBe('Yearly')
        ->and(Server::factory()->withInterval(4)->make()->intervalLabel())->toBe('Every 4 months');
});
