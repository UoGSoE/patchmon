<?php

use App\Enums\GraceUnit;
use App\Livewire\AdminDashboard;
use App\Models\EstateSnapshot;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('forbids non-admins from the admin dashboard', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
});

it('shows the admin dashboard to admin users', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
});

it('shows the admin dashboard to oversight admins who are not full admins', function () {
    $oversightAdmin = User::factory()->oversightAdmin()->create(['is_admin' => false]);

    $this->actingAs($oversightAdmin)->get(route('admin.dashboard'))->assertOk();
});

it('still forbids oversight admins from the other admin pages', function () {
    $oversightAdmin = User::factory()->oversightAdmin()->create(['is_admin' => false]);

    $this->actingAs($oversightAdmin)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($oversightAdmin)->get(route('admin.teams.index'))->assertForbidden();
});

it('shows the dashboard nav link to oversight admins, labelled Dashboard, but not to plain users', function () {
    $oversightAdmin = User::factory()->oversightAdmin()->create(['is_admin' => false]);
    $plainUser = User::factory()->create(['is_admin' => false]);

    $this->actingAs($oversightAdmin)->get(route('home'))->assertSee('Dashboard');
    $this->actingAs($plainUser)->get(route('home'))->assertDontSee('Dashboard');
});

it('hides the admin-only Manage cards from oversight admins but shows them to admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $oversightAdmin = User::factory()->oversightAdmin()->create(['is_admin' => false]);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertSee('Edit, promote and remove user accounts.');

    $this->actingAs($oversightAdmin)->get(route('admin.dashboard'))
        ->assertDontSee('Edit, promote and remove user accounts.')
        ->assertSee('Patching overview');
});

it('computes the four summary card numbers', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();

    // Two healthy servers — recently created, not overdue, not silenced
    Server::factory()->count(2)->forTeam($team)->create();

    // One overdue (last patched 2 months ago, monthly cadence + 7 day grace)
    Server::factory()->forTeam($team)->overdue()->create();

    // One silenced AND past deadline — should NOT count as overdue
    Server::factory()->forTeam($team)->overdue()->silenced()->create();

    // One server with a recent patch event — should count in "patched 30d"
    $recentlyPatched = Server::factory()->forTeam($team)->create();
    $recentlyPatched->recordPatch(at: now()->subDays(3));

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertViewHas('totalCount', 5)
        ->assertViewHas('overdueCount', 1)
        ->assertViewHas('silencedCount', 1)
        ->assertViewHas('patchedRecentlyCount', 1);
});

it('counts servers that have never checked in, across all live servers regardless of team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();

    // Never patched, owned — counts.
    Server::factory()->forTeam($team)->create(['last_patched_at' => null]);

    // Never patched, in triage (no team) — counts: deliberately wider than the
    // monitored estate, because a half-set-up triage server is exactly the kind
    // of "something's wrong here" case this card is meant to surface.
    Server::factory()->unassigned()->create(['last_patched_at' => null]);

    // Never patched but decommissioned — excluded (a retired box that never
    // reported is expected, not a problem).
    Server::factory()->forTeam($team)->inactive()->create(['last_patched_at' => null]);

    // Has checked in — excluded.
    Server::factory()->forTeam($team)->create(['last_patched_at' => now()->subDays(10)]);

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertViewHas('neverCheckedInCount', 2);
});

it('explains the never-checked-in figure in plain English for senior management', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertSee('never reported being patched');
});

it('excludes inactive and unassigned servers from the dashboard figures', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();

    // The only server the dashboard should count: a live, owned, overdue server.
    Server::factory()->forTeam($team)->overdue()->create(['name' => 'live-overdue.example.test']);

    // Decommissioned (dropped out of NetBox) — overdue, but the evaluator ignores it.
    Server::factory()->forTeam($team)->overdue()->inactive()->create(['name' => 'decommissioned.example.test']);

    // In triage (no team) — overdue, but the evaluator ignores it.
    Server::factory()->unassigned()->overdue()->create(['name' => 'triage-overdue.example.test']);

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertViewHas('totalCount', 1)
        ->assertViewHas('overdueCount', 1)
        ->assertViewHas('overdueServers', function ($servers) {
            return $servers->pluck('name')->all() === ['live-overdue.example.test'];
        });
});

it('lists overdue non-silenced servers most-overdue-first and excludes silenced ones', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();

    // Mild overdue — 1-month interval, 7-day grace, last patched 2 months ago
    $mildlyOverdue = Server::factory()
        ->forTeam($team)
        ->create(['name' => 'mild.example.test', 'last_patched_at' => now()->subMonths(2)]);

    // Severely overdue — same cadence but last patched 6 months ago
    $severelyOverdue = Server::factory()
        ->forTeam($team)
        ->create(['name' => 'severe.example.test', 'last_patched_at' => now()->subMonths(6)]);

    // Silenced AND past deadline — must not appear
    Server::factory()
        ->forTeam($team)
        ->overdue()
        ->silenced()
        ->create(['name' => 'silenced.example.test']);

    // Healthy server — must not appear
    Server::factory()->forTeam($team)->create(['name' => 'healthy.example.test']);

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertViewHas('overdueServers', function ($servers) use ($severelyOverdue, $mildlyOverdue) {
            return $servers->pluck('id')->all() === [$severelyOverdue->id, $mildlyOverdue->id];
        })
        ->assertSee('severe.example.test')
        ->assertSee('mild.example.test')
        ->assertDontSee('silenced.example.test')
        ->assertDontSee('healthy.example.test');
});

it('shows a reassuring empty state when nothing is overdue', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();
    Server::factory()->count(3)->forTeam($team)->create();

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertSee('Nothing overdue');
});

it('breaks the overdue total into 1–7 / 8–30 / 30+ day severity bands on the card', function () {
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();

    // One mildly overdue (3 days) and one severely overdue (60 days). Zero grace
    // so the deadline is exactly last_patched + 1 month.
    Server::factory()->forTeam($team)->withGrace(0, GraceUnit::Days)
        ->create(['last_patched_at' => now()->subDays(3)->subMonthsNoOverflow(1)]);
    Server::factory()->forTeam($team)->withGrace(0, GraceUnit::Days)
        ->create(['last_patched_at' => now()->subDays(60)->subMonthsNoOverflow(1)]);

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertViewHas('overdueSeverityBands', [
            'mild' => 1,
            'moderate' => 0,
            'severe' => 1,
        ])
        ->assertSee('1–7')
        ->assertSee('8–30')
        ->assertSee('30+');
});

it('feeds the overdue-percentage trend series to the chart from daily snapshots, oldest first', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    EstateSnapshot::factory()->create(['snapshot_date' => '2026-06-03', 'total' => 200, 'overdue' => 20]);
    EstateSnapshot::factory()->create(['snapshot_date' => '2026-06-01', 'total' => 100, 'overdue' => 10]);
    EstateSnapshot::factory()->create(['snapshot_date' => '2026-06-02', 'total' => 100, 'overdue' => 20]);

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertViewHas('trendSeries', [
            ['date' => '2026-06-01', 'overdue_pct' => 0.1],
            ['date' => '2026-06-02', 'overdue_pct' => 0.2],
            ['date' => '2026-06-03', 'overdue_pct' => 0.1],
        ])
        ->assertSee('Overdue trend');
});

it('compares overdue % now against a month, quarter and year ago from snapshots, flagging a missing baseline', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    EstateSnapshot::factory()->create(['snapshot_date' => today(), 'total' => 100, 'overdue' => 5]);
    EstateSnapshot::factory()->create(['snapshot_date' => today()->subMonthsNoOverflow(1), 'total' => 100, 'overdue' => 10]);
    EstateSnapshot::factory()->create(['snapshot_date' => today()->subMonthsNoOverflow(3), 'total' => 100, 'overdue' => 20]);
    // Nothing reaches back ~1 year — that baseline should be null.

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertViewHas('comparisonBars', [
            ['period' => 'Now', 'overdue_pct' => 0.05],
            ['period' => '1 month ago', 'overdue_pct' => 0.1],
            ['period' => '1 quarter ago', 'overdue_pct' => 0.2],
        ])
        ->assertViewHas('comparisonMissing', ['1 year ago'])
        ->assertSee('Overdue: now vs the past');
});

it('notes which comparison baselines are not available yet', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    // Only today's snapshot exists, so every past baseline is missing.
    EstateSnapshot::factory()->create(['snapshot_date' => today(), 'total' => 100, 'overdue' => 5]);

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertSee('No baseline yet for: 1 month ago, 1 quarter ago, 1 year ago');
});

it('shows a friendly message instead of the trend chart until there are at least two snapshots', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    EstateSnapshot::factory()->create(['snapshot_date' => today(), 'total' => 100, 'overdue' => 10]);

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertSee('Overdue trend')
        ->assertSee('Not enough history yet');
});

it('limits the trend chart to the selected time range', function () {
    $this->travelTo(Carbon::parse('2026-06-22'));
    $admin = User::factory()->create(['is_admin' => true]);

    // Both within the last month.
    EstateSnapshot::factory()->create(['snapshot_date' => '2026-06-10', 'total' => 100, 'overdue' => 10]);
    EstateSnapshot::factory()->create(['snapshot_date' => '2026-06-20', 'total' => 100, 'overdue' => 8]);
    // Five months back — in range for a year, but not for a month.
    EstateSnapshot::factory()->create(['snapshot_date' => '2026-01-15', 'total' => 100, 'overdue' => 30]);

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->set('trendRange', 'month')
        ->assertViewHas('trendSeries', [
            ['date' => '2026-06-10', 'overdue_pct' => 0.1],
            ['date' => '2026-06-20', 'overdue_pct' => 0.08],
        ]);
});

it('thins out a long, noisy trend so the line stays readable, keeping both ends', function () {
    $this->travelTo(Carbon::parse('2026-06-22'));
    $admin = User::factory()->create(['is_admin' => true]);

    // 100 daily snapshots, all within the default year range.
    foreach (range(1, 100) as $daysAgo) {
        EstateSnapshot::factory()->create([
            'snapshot_date' => today()->subDays($daysAgo),
            'total' => 100,
            'overdue' => 10,
        ]);
    }

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertViewHas('trendSeries', function (array $series) {
            return count($series) >= 2
                && count($series) <= 30
                && $series[0]['date'] === today()->subDays(100)->format('Y-m-d')
                && end($series)['date'] === today()->subDays(1)->format('Y-m-d');
        });
});

it('defaults the trend to a year and offers shorter ranges to choose from', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertSet('trendRange', 'year')
        ->assertSee('6 months')
        ->assertSee('Quarter');
});

it('builds a per-team breakdown row for each team that has servers', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $linux = Team::factory()->create(['name' => 'Linux Infra']);
    $windows = Team::factory()->create(['name' => 'Windows Server']);
    Team::factory()->create(['name' => 'New Team']);

    // Linux: 2 healthy
    Server::factory()->count(2)->forTeam($linux)->create();

    // Windows: 1 overdue (non-silenced), 1 silenced
    Server::factory()->forTeam($windows)->overdue()->create();
    Server::factory()->forTeam($windows)->silenced()->create();

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertViewHas('teamRows', function ($rows) use ($linux, $windows) {
            $linuxRow = $rows->firstWhere('team.id', $linux->id);
            $windowsRow = $rows->firstWhere('team.id', $windows->id);

            return $rows->count() === 2
                && $linuxRow['total'] === 2
                && $linuxRow['overdue'] === 0
                && $linuxRow['silenced'] === 0
                && $windowsRow['total'] === 2
                && $windowsRow['overdue'] === 1
                && $windowsRow['silenced'] === 1;
        });
});

it('flags worst-in-column with direction per column in percent mode', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    // Linux: 10 servers, 0 overdue, 0 silenced, 5 patched recently
    $linux = Team::factory()->create(['name' => 'Linux Infra']);
    $linuxServers = Server::factory()->count(10)->forTeam($linux)->create();
    foreach ($linuxServers->take(5) as $server) {
        $server->recordPatch(at: now()->subDays(1));
    }

    // Windows: 10 servers, 4 overdue (40%), 2 silenced (20%), 1 patched (10%)
    // Windows is worst in all three: most overdue, most silenced, fewest patched
    $windows = Team::factory()->create(['name' => 'Windows Server']);
    Server::factory()->count(4)->forTeam($windows)->overdue()->create();
    Server::factory()->count(2)->forTeam($windows)->silenced()->create();
    $windowsHealthy = Server::factory()->count(4)->forTeam($windows)->create();
    $windowsHealthy->first()->recordPatch(at: now()->subDays(1));

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertSet('mode', 'percent')
        ->assertViewHas('teamRows', function ($rows) use ($linux, $windows) {
            $linuxRow = $rows->firstWhere('team.id', $linux->id);
            $windowsRow = $rows->firstWhere('team.id', $windows->id);

            return $windowsRow['overdue_is_worst'] === true
                && $windowsRow['silenced_is_worst'] === true
                && $windowsRow['patched_30d_is_worst'] === true
                && $linuxRow['overdue_is_worst'] === false
                && $linuxRow['silenced_is_worst'] === false
                && $linuxRow['patched_30d_is_worst'] === false;
        });
});

it('buckets every server by time since last patch with a separate never-patched bucket', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();

    // 2 servers in ≤30d
    Server::factory()->count(2)->forTeam($team)->create(['last_patched_at' => now()->subDays(5)]);
    // 3 in 31-90d
    Server::factory()->count(3)->forTeam($team)->create(['last_patched_at' => now()->subDays(45)]);
    // 1 in 91-180d
    Server::factory()->forTeam($team)->create(['last_patched_at' => now()->subDays(120)]);
    // 4 in 180+d
    Server::factory()->count(4)->forTeam($team)->create(['last_patched_at' => now()->subDays(300)]);
    // 2 never patched (last_patched_at null by default)
    Server::factory()->count(2)->forTeam($team)->create(['last_patched_at' => null]);

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->assertViewHas('postureBuckets', [
            'fresh' => 2,
            'recent' => 3,
            'stale' => 1,
            'old' => 4,
            'never' => 2,
        ]);
});

it('switches the worst-in-column basis when the mode flips to absolute', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    // Small team — 2 servers, both overdue → 100% overdue but only 2 in absolute count
    $small = Team::factory()->create(['name' => 'Small Team']);
    Server::factory()->count(2)->forTeam($small)->overdue()->create();

    // Big team — 20 servers, 5 overdue → 25% overdue but 5 in absolute count
    $big = Team::factory()->create(['name' => 'Big Team']);
    Server::factory()->count(5)->forTeam($big)->overdue()->create();
    Server::factory()->count(15)->forTeam($big)->create();

    Livewire::actingAs($admin)
        ->test(AdminDashboard::class)
        ->set('mode', 'absolute')
        ->assertViewHas('teamRows', function ($rows) use ($small, $big) {
            $smallRow = $rows->firstWhere('team.id', $small->id);
            $bigRow = $rows->firstWhere('team.id', $big->id);

            return $bigRow['overdue_is_worst'] === true
                && $smallRow['overdue_is_worst'] === false;
        });
});
