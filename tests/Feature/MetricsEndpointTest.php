<?php

use App\Models\Server;
use App\Models\Team;

beforeEach(function () {
    config(['patchmon.metrics.token' => 'super-secret']);
});

it('returns 503 when no metrics token has been configured', function () {
    config(['patchmon.metrics.token' => null]);

    $this->withToken('anything')->get('/metrics')->assertStatus(503);
    $this->get('/metrics')->assertStatus(503);
});

it('rejects a scrape with a missing or wrong bearer token', function () {
    $this->get('/metrics')->assertForbidden();
    $this->withToken('wrong')->get('/metrics')->assertForbidden();
});

it('exposes the estate metrics in Prometheus exposition format for a valid token', function () {
    $alpha = Team::factory()->create(['name' => 'Alpha']);
    $beta = Team::factory()->create(['name' => 'Beta']);

    // Alpha: one overdue, one patched in the last 30 days.
    Server::factory()->forTeam($alpha)->overdue()->create();
    Server::factory()->forTeam($alpha)->create()->recordPatch(at: now()->subDays(2));

    // Beta: one silenced, last patched 40 days ago (so not "recently").
    Server::factory()->forTeam($beta)->silenced()->create(['last_patched_at' => now()->subDays(40)]);

    // Unassigned, never patched — the single estate-wide never-checked-in server.
    Server::factory()->unassigned()->create(['last_patched_at' => null]);

    $response = $this->withToken('super-secret')->get('/metrics');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain')
        ->and($response->getContent())->toBe(file_get_contents(base_path('tests/fixtures/metrics.txt')));
});
