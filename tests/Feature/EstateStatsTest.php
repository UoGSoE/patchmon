<?php

use App\Enums\GraceUnit;
use App\Models\Server;
use App\Models\Team;
use App\Services\EstateStats;
use Illuminate\Support\Carbon;

it('summarises the monitored-estate counts', function () {
    $team = Team::factory()->create();

    // Monitored + healthy: a real patch event 5 days ago.
    $healthy = Server::factory()->forTeam($team)->create();
    $healthy->recordPatch(at: now()->subDays(5));

    // Monitored + overdue (overdue() sets last_patched_at two months back).
    Server::factory()->forTeam($team)->overdue()->create();

    // Monitored + silenced past deadline — not counted as overdue.
    Server::factory()->forTeam($team)->overdue()->silenced()->create();

    // Monitored + never patched.
    Server::factory()->forTeam($team)->create(['last_patched_at' => null]);

    // Triage (no team) + never patched — counts for never-checked-in but NOT for
    // the monitored total.
    Server::factory()->unassigned()->create(['last_patched_at' => null]);

    $stats = new EstateStats;

    expect($stats->totalCount())->toBe(4)
        ->and($stats->overdueCount())->toBe(1)
        ->and($stats->silencedCount())->toBe(1)
        ->and($stats->patchedRecentlyCount())->toBe(1)
        ->and($stats->neverCheckedInCount())->toBe(2);
});

it('scopes the recent-patch count to the monitored estate, excluding decommissioned servers', function () {
    $team = Team::factory()->create();

    $monitored = Server::factory()->forTeam($team)->create();
    $monitored->recordPatch(at: now()->subDays(2));

    // Decommissioned: patched recently, but outside the monitored estate, so it
    // must not inflate the "patched in 30 days" figure (consistent with how
    // total / overdue / silenced already exclude it).
    $inactive = Server::factory()->forTeam($team)->inactive()->create();
    $inactive->recordPatch(at: now()->subDays(2));

    $stats = new EstateStats;

    expect($stats->totalCount())->toBe(1)
        ->and($stats->patchedRecentlyCount())->toBe(1);
});

it('buckets monitored servers by time since last patch', function () {
    $team = Team::factory()->create();

    Server::factory()->count(2)->forTeam($team)->create(['last_patched_at' => now()->subDays(5)]);
    Server::factory()->forTeam($team)->create(['last_patched_at' => now()->subDays(45)]);
    Server::factory()->forTeam($team)->create(['last_patched_at' => now()->subDays(120)]);
    Server::factory()->count(3)->forTeam($team)->create(['last_patched_at' => now()->subDays(300)]);
    Server::factory()->forTeam($team)->create(['last_patched_at' => null]);

    expect((new EstateStats)->postureBuckets())->toBe([
        'fresh' => 2,
        'recent' => 1,
        'stale' => 1,
        'old' => 3,
        'never' => 1,
    ]);
});

it('splits overdue servers into 1–7 / 8–30 / 30+ day severity bands, excluding silenced ones', function () {
    // Freeze mid-month so the deadline arithmetic can't drift across a month boundary.
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

    $team = Team::factory()->create();

    // A server whose deadline lands exactly $days in the past: monthly cadence,
    // zero grace, so deadline = last_patched + 1 month.
    $overdueByDays = fn (int $days) => Server::factory()
        ->forTeam($team)
        ->withGrace(0, GraceUnit::Days)
        ->create(['last_patched_at' => now()->subDays($days)->subMonthsNoOverflow(1)]);

    $overdueByDays(0);   // only just crossed the deadline → first band
    $overdueByDays(7);   // boundary → 1–7
    $overdueByDays(8);   // boundary → 8–30
    $overdueByDays(30);  // boundary → 8–30
    $overdueByDays(31);  // boundary → 30+

    // Silenced and severely overdue — must not appear in the bands at all.
    Server::factory()->forTeam($team)->overdue()->silenced()->create();

    $stats = new EstateStats;
    $bands = $stats->overdueSeverityBands();

    expect($bands)->toBe([
        'mild' => 2,     // 0 and 7 days
        'moderate' => 2, // 8 and 30 days
        'severe' => 1,   // 31 days
    ])->and(array_sum($bands))->toBe($stats->overdueCount());
});

it('builds raw per-team rows (counts and percentages) only for teams with servers', function () {
    $linux = Team::factory()->create(['name' => 'Linux Infra']);
    $windows = Team::factory()->create(['name' => 'Windows Server']);
    Team::factory()->create(['name' => 'Empty Team']);

    Server::factory()->count(2)->forTeam($linux)->create(['last_patched_at' => now()->subDays(5)]);

    Server::factory()->forTeam($windows)->overdue()->create();
    Server::factory()->forTeam($windows)->silenced()->create(['last_patched_at' => now()->subDays(5)]);

    $rows = (new EstateStats)->teamRows();

    $linuxRow = $rows->firstWhere('team.id', $linux->id);
    $windowsRow = $rows->firstWhere('team.id', $windows->id);

    // Raw rows carry counts + percentages but NOT the worst-in-column flags —
    // that marking is a view concern (it depends on the dashboard's mode toggle).
    expect($rows)->toHaveCount(2)
        ->and($linuxRow['total'])->toBe(2)
        ->and($linuxRow['overdue'])->toBe(0)
        ->and($windowsRow['total'])->toBe(2)
        ->and($windowsRow['overdue'])->toBe(1)
        ->and($windowsRow['silenced'])->toBe(1)
        ->and($windowsRow['overdue_pct'])->toBe(50.0)
        ->and($windowsRow)->not->toHaveKey('overdue_is_worst');
});
