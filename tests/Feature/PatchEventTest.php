<?php

use App\Models\PatchEvent;
use App\Models\Server;
use Carbon\CarbonInterface;

it('recordPatchEvent stamps the check-in with an explicit timestamp when one is provided', function () {
    $server = Server::factory()->create();
    $requestedAt = now()->subSeconds(30)->startOfSecond();

    $patchEvent = $server->recordPatchEvent('203.0.113.5', $requestedAt);

    $server->refresh();

    expect($patchEvent->patched_at->equalTo($requestedAt))->toBeTrue()
        ->and($server->last_patched_at->equalTo($requestedAt))->toBeTrue();
});

it('recordPatchEvent logs the ping and clears any alerting state on the job', function () {
    $server = Server::factory()->alerting()->create([
        'last_patched_at' => now()->subDay(),
    ]);

    $server->recordPatchEvent('203.0.113.5');

    $server->refresh();

    expect($server->patchEvents)->toHaveCount(1)
        ->and($server->patchEvents->first()->source_ip)->toBe('203.0.113.5')
        ->and($server->last_patched_at->diffInSeconds(now()))->toBeLessThan(2)
        ->and($server->alerting_since)->toBeNull()
        ->and($server->last_alerted_at)->toBeNull();
});

it('records a check-in against a job and exposes it via the relation', function () {
    $server = Server::factory()->create();

    $patchEvent = PatchEvent::factory()->for($server)->create([
        'source_ip' => '10.0.0.42',
    ]);

    expect($patchEvent->server->is($server))->toBeTrue()
        ->and($patchEvent->patched_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($patchEvent->source_ip)->toBe('10.0.0.42')
        ->and($server->fresh()->patchEvents->pluck('id'))->toContain($patchEvent->id);
});
