<?php

use App\Events\ActivityOccurred;
use App\Models\ActivityLog;
use App\Models\Server;
use App\Models\User;

it('records an activity log row with name snapshots resolved from ids', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create();

    ActivityOccurred::dispatch($user->id, $server->id, 'Did a thing', '10.0.0.1');

    $log = ActivityLog::sole();

    expect($log->user_id)->toBe($user->id);
    expect($log->user_name)->toBe($user->full_name);
    expect($log->server_id)->toBe($server->id);
    expect($log->server_name)->toBe($server->name);
    expect($log->description)->toBe('Did a thing');
    expect($log->source_ip)->toBe('10.0.0.1');
});

it('records an unattributed activity with no user or server', function () {
    ActivityOccurred::dispatch(null, null, 'Something automated happened', null);

    $log = ActivityLog::sole();

    expect($log->user_id)->toBeNull();
    expect($log->user_name)->toBeNull();
    expect($log->server_id)->toBeNull();
    expect($log->server_name)->toBeNull();
    expect($log->actorLabel())->toBe('Automated');
});

it('writes an activity row when a patch is recorded', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create();

    $server->recordPatch($user, 'all good', '10.0.0.5');

    $log = ActivityLog::sole();

    expect($log->user_id)->toBe($user->id);
    expect($log->server_id)->toBe($server->id);
    expect($log->source_ip)->toBe('10.0.0.5');
    expect($log->description)->toBe('Recorded a patch');
});

it('writes an activity row when a server is silenced', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create();

    $server->silenceBetween(now(), now()->addWeek(), 'maintenance window', $user, '10.0.0.9');

    $log = ActivityLog::sole();

    expect($log->user_id)->toBe($user->id);
    expect($log->server_id)->toBe($server->id);
    expect($log->source_ip)->toBe('10.0.0.9');
    expect($log->description)->toStartWith('Silenced the server');
});

it('writes an activity row when a server is unsilenced', function () {
    $user = User::factory()->create();
    $server = Server::factory()->silenced()->create();

    $server->unsilence($user, '10.0.0.9');

    $log = ActivityLog::sole();

    expect($log->user_id)->toBe($user->id);
    expect($log->server_id)->toBe($server->id);
    expect($log->description)->toBe('Unsilenced the server');
});
