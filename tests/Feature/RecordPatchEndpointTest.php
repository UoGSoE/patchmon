<?php

use App\Jobs\RecordPatchEvent;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('returns 404 and queues nothing when the token does not match any job', function () {
    Queue::fake();

    Server::factory()->create();

    $response = $this->get('/record-patch/00000000-0000-0000-0000-000000000000');

    $response->assertNotFound();
    Queue::assertNothingPushed();
});

it('still queues a check-in for a silenced job so forensic history stays complete', function () {
    Queue::fake();

    $server = Server::factory()->silenced()->create();

    $response = $this->get('/record-patch/'.$server->patch_token);

    $response->assertOk();
    Queue::assertPushed(RecordPatchEvent::class, fn (RecordPatchEvent $queued) => $queued->serverId === $server->id);
});

it('records the check-in against the right job and timestamp when the queued job runs', function () {
    $server = Server::factory()->alerting()->create();
    $at = now()->subSeconds(30)->startOfSecond();

    (new RecordPatchEvent($server->id, '198.51.100.7', $at))->handle();

    $server->refresh();

    expect($server->patchEvents)->toHaveCount(1)
        ->and($server->patchEvents->first()->source_ip)->toBe('198.51.100.7')
        ->and($server->last_patched_at->equalTo($at))->toBeTrue()
        ->and($server->alerting_since)->toBeNull();
});

it('queues a check-in job when a job receives a ping at its token URL', function () {
    Queue::fake();

    $server = Server::factory()->create();

    $response = $this->get('/record-patch/'.$server->patch_token);

    $response->assertOk();
    Queue::assertPushed(RecordPatchEvent::class, fn (RecordPatchEvent $queued) => $queued->serverId === $server->id);
});

it('attributes the patch to the bearer-token owner and passes notes through to the queued job', function () {
    Queue::fake();

    $alice = User::factory()->create();
    $accessToken = $alice->createToken('test')->plainTextToken;
    $server = Server::factory()->create();

    $response = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->postJson('/record-patch/'.$server->patch_token, ['notes' => 'Rebooted twice']);

    $response->assertOk();
    Queue::assertPushed(
        RecordPatchEvent::class,
        fn (RecordPatchEvent $queued) => $queued->serverId === $server->id
            && $queued->patchedBy === $alice->id
            && $queued->notes === 'Rebooted twice',
    );
});

it('records anonymously and prepends a marker to the notes when the bearer token is invalid', function () {
    Queue::fake();

    $server = Server::factory()->create();

    $response = $this->withHeader('Authorization', 'Bearer not-a-real-token')
        ->postJson('/record-patch/'.$server->patch_token, ['notes' => 'Rebooted twice']);

    $response->assertOk();
    Queue::assertPushed(
        RecordPatchEvent::class,
        fn (RecordPatchEvent $queued) => $queued->serverId === $server->id
            && $queued->patchedBy === null
            && str_contains($queued->notes, 'invalid')
            && str_contains($queued->notes, 'Rebooted twice'),
    );
});

it('records anonymously with no notes marker when no bearer token is supplied', function () {
    Queue::fake();

    $server = Server::factory()->create();

    $response = $this->postJson('/record-patch/'.$server->patch_token, ['notes' => 'From SCCM']);

    $response->assertOk();
    Queue::assertPushed(
        RecordPatchEvent::class,
        fn (RecordPatchEvent $queued) => $queued->patchedBy === null && $queued->notes === 'From SCCM',
    );
});
