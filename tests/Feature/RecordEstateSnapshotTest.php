<?php

use App\Models\EstateSnapshot;
use App\Models\Server;
use App\Models\Team;
use App\Services\EstateStats;

it('upserts the day\'s row rather than duplicating when run more than once', function () {
    $team = Team::factory()->create();
    Server::factory()->forTeam($team)->overdue()->create();

    $this->artisan('patchmon:snapshot')->assertSuccessful();

    // A second server goes overdue later the same day; the command runs again.
    Server::factory()->forTeam($team)->overdue()->create();
    $this->artisan('patchmon:snapshot')->assertSuccessful();

    expect(EstateSnapshot::count())->toBe(1)
        ->and(EstateSnapshot::sole()->overdue)->toBe(2);
});

it('records a daily snapshot of the estate counts', function () {
    $team = Team::factory()->create();

    // Monitored + healthy (patched recently).
    $healthy = Server::factory()->forTeam($team)->create();
    $healthy->recordPatch(at: now()->subDays(5));
    // Monitored + overdue.
    Server::factory()->forTeam($team)->overdue()->create();
    // Monitored + silenced past deadline — not counted as overdue.
    Server::factory()->forTeam($team)->overdue()->silenced()->create();
    // Monitored + never patched.
    Server::factory()->forTeam($team)->create(['last_patched_at' => null]);
    // Triage (no team) + never patched — counts for never-checked-in only.
    Server::factory()->unassigned()->create(['last_patched_at' => null]);

    $this->artisan('patchmon:snapshot')->assertSuccessful();

    $stats = new EstateStats;
    $snapshot = EstateSnapshot::sole();

    expect($snapshot->snapshot_date->isToday())->toBeTrue()
        ->and($snapshot->total)->toBe($stats->totalCount())
        ->and($snapshot->overdue)->toBe($stats->overdueCount())
        ->and($snapshot->silenced)->toBe($stats->silencedCount())
        ->and($snapshot->patched_30d)->toBe($stats->patchedRecentlyCount())
        ->and($snapshot->never_checked_in)->toBe($stats->neverCheckedInCount());
});
