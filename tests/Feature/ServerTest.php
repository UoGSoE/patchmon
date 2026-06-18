<?php

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

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

it('silenceBetween persists the start, end, and reason on the server', function () {
    $server = Server::factory()->create();
    $from = now()->subHour()->startOfSecond();
    $until = now()->addDay()->startOfSecond();

    $server->silenceBetween($from, $until, 'Power works in the data centre');

    $server->refresh();
    expect($server->silenced_from->equalTo($from))->toBeTrue()
        ->and($server->silenced_until->equalTo($until))->toBeTrue()
        ->and($server->silence_reason)->toBe('Power works in the data centre')
        ->and($server->isCurrentlySilenced())->toBeTrue();
});

it('unsilence clears the start, end, and reason on the server', function () {
    $server = Server::factory()->silenced()->create();

    $server->unsilence();

    $server->refresh();
    expect($server->silenced_from)->toBeNull()
        ->and($server->silenced_until)->toBeNull()
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

it('can be created without a team or a creator (sync-sourced servers land in triage)', function () {
    $server = Server::factory()->create([
        'team_id' => null,
        'created_by_user_id' => null,
    ]);

    expect($server->team_id)->toBeNull()
        ->and($server->created_by_user_id)->toBeNull();
});

it('stores and casts the netbox sync columns', function () {
    $server = Server::factory()->create([
        'netbox_id' => 42,
        'is_virtual' => true,
        'inactive_since' => now(),
    ]);

    $fresh = $server->fresh();

    expect($fresh->netbox_id)->toBe(42)
        ->and($fresh->is_virtual)->toBeTrue()
        ->and($fresh->inactive_since)->toBeInstanceOf(Carbon::class);
});

it('provides factory states for the netbox sync lifecycle', function () {
    $plain = Server::factory()->create();
    $virtual = Server::factory()->virtual()->create();
    $fromNetbox = Server::factory()->fromNetbox(7)->create();
    $inactive = Server::factory()->inactive()->create();
    $unassigned = Server::factory()->unassigned()->create();

    expect($plain->is_virtual)->toBeFalse()
        ->and($virtual->is_virtual)->toBeTrue()
        ->and($fromNetbox->netbox_id)->toBe(7)
        ->and($inactive->inactive_since)->not->toBeNull()
        ->and($unassigned->team_id)->toBeNull()
        ->and($unassigned->created_by_user_id)->toBeNull();
});

it('treats a netbox device and vm with the same id as distinct, but rejects a duplicate', function () {
    Server::factory()->fromNetbox(5, isVirtual: false)->create();
    Server::factory()->fromNetbox(5, isVirtual: true)->create();

    expect(fn () => Server::factory()->fromNetbox(5, isVirtual: false)->create())
        ->toThrow(QueryException::class)
        ->and(Server::count())->toBe(2);
});

it('allows many servers with no netbox id under the composite index', function () {
    Server::factory()->count(3)->create();

    expect(Server::count())->toBe(3);
});
