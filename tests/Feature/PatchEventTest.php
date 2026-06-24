<?php

use App\Models\PatchEvent;
use App\Models\Server;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Event;

it('recordPatch stamps the patch event with an explicit timestamp when one is provided', function () {
    $server = Server::factory()->create();
    $requestedAt = now()->subSeconds(30)->startOfSecond();

    $patchEvent = $server->recordPatch(null, null, '203.0.113.5', $requestedAt);

    $server->refresh();

    expect($patchEvent->patched_at->equalTo($requestedAt))->toBeTrue()
        ->and($server->last_patched_at->equalTo($requestedAt))->toBeTrue();
});

it('recordPatch logs the patch and clears any alerting state on the server', function () {
    $server = Server::factory()->alerting()->create([
        'last_patched_at' => now()->subDay(),
    ]);

    $server->recordPatch(null, null, '203.0.113.5');

    $server->refresh();

    expect($server->patchEvents)->toHaveCount(1)
        ->and($server->patchEvents->first()->source_ip)->toBe('203.0.113.5')
        ->and($server->last_patched_at->diffInSeconds(now()))->toBeLessThan(2)
        ->and($server->alerting_since)->toBeNull()
        ->and($server->last_alerted_at)->toBeNull();
});

it('attributes recordPatch to a user when one is supplied', function () {
    $server = Server::factory()->create();
    $sysadmin = User::factory()->create();

    $patchEvent = $server->recordPatch($sysadmin, 'Reboot required after libssl upgrade.');

    expect($patchEvent->patched_by)->toBe($sysadmin->id)
        ->and($patchEvent->patchedBy->is($sysadmin))->toBeTrue()
        ->and($patchEvent->notes)->toBe('Reboot required after libssl upgrade.');
});

it('leaves patched_by null and notes null when recordPatch is called anonymously', function () {
    $server = Server::factory()->create();

    $patchEvent = $server->recordPatch();

    expect($patchEvent->patched_by)->toBeNull()
        ->and($patchEvent->notes)->toBeNull();
});

it('exposes patch events via the server relation', function () {
    $server = Server::factory()->create();

    $patchEvent = PatchEvent::factory()->for($server)->create([
        'source_ip' => '10.0.0.42',
    ]);

    expect($patchEvent->server->is($server))->toBeTrue()
        ->and($patchEvent->patched_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($patchEvent->source_ip)->toBe('10.0.0.42')
        ->and($server->fresh()->patchEvents->pluck('id'))->toContain($patchEvent->id);
});

it('rolls back the patch event when updating the server fails', function () {
    $server = Server::factory()->create([
        'last_patched_at' => null,
    ]);

    Event::listen('eloquent.saving: '.Server::class, function (): void {
        throw new RuntimeException('Simulated server save failure.');
    });

    expect(fn () => $server->recordPatch(null, 'Rebooted.', '203.0.113.5'))
        ->toThrow(RuntimeException::class);

    expect($server->patchEvents()->count())->toBe(0)
        ->and($server->fresh()->last_patched_at)->toBeNull();
});
