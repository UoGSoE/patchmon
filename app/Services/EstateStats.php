<?php

namespace App\Services;

use App\Models\PatchEvent;
use App\Models\Server;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * The single source of truth for the patching estate summary, shared by the
 * management dashboard, the weekly overview email, and (later) the daily
 * snapshot and the Prometheus endpoint. See ADR patchmon-uqwCr.
 */
class EstateStats
{
    /** @var Collection<int, Server>|null */
    private ?Collection $monitoredServers = null;

    /** @var array<int, int>|null */
    private ?array $recentlyPatchedServerIds = null;

    /**
     * The live, monitored estate: servers that belong to a team and are not
     * decommissioned — exactly the set the evaluator alerts on.
     *
     * @return Collection<int, Server>
     */
    public function monitoredServers(): Collection
    {
        return $this->monitoredServers ??= Server::monitored()->with('team')->get();
    }

    public function totalCount(): int
    {
        return $this->monitoredServers()->count();
    }

    /**
     * @return Collection<int, Server>
     */
    public function overdueServers(): Collection
    {
        return $this->monitoredServers()
            ->filter(fn (Server $server) => $server->isOverdue() && ! $server->isCurrentlySilenced())
            ->sortBy(fn (Server $server) => $server->deadline()->timestamp)
            ->values();
    }

    public function overdueCount(): int
    {
        return $this->overdueServers()->count();
    }

    public function silencedCount(): int
    {
        return $this->monitoredServers()
            ->filter(fn (Server $server) => $server->isCurrentlySilenced())
            ->count();
    }

    /**
     * Distinct monitored servers with a patch event in the last 30 days — proof
     * of activity for the estate we actually watch. Scoped to the monitored set
     * for consistency with total / overdue / silenced.
     */
    public function patchedRecentlyCount(): int
    {
        return count($this->recentlyPatchedServerIds());
    }

    public function neverCheckedInCount(): int
    {
        return Server::neverCheckedIn()->count();
    }

    /**
     * Overdue (non-silenced) servers bucketed by how far past their deadline,
     * so management can tell a couple of days late from a month-plus late at a
     * glance. The bands sum to overdueCount(); a server that has only just
     * crossed its deadline (daysOverdue 0) falls into the first band.
     *
     * @return array<string, int>
     */
    public function overdueSeverityBands(): array
    {
        $bands = ['mild' => 0, 'moderate' => 0, 'severe' => 0];

        foreach ($this->overdueServers() as $server) {
            $bands[match (true) {
                $server->daysOverdue() <= 7 => 'mild',
                $server->daysOverdue() <= 30 => 'moderate',
                default => 'severe',
            }]++;
        }

        return $bands;
    }

    /**
     * Monitored servers bucketed by how long since their last patch.
     *
     * @return array<string, int>
     */
    public function postureBuckets(): array
    {
        $buckets = ['fresh' => 0, 'recent' => 0, 'stale' => 0, 'old' => 0, 'never' => 0];

        foreach ($this->monitoredServers() as $server) {
            if ($server->last_patched_at === null) {
                $buckets['never']++;

                continue;
            }

            $days = $server->last_patched_at->diffInDays(now());

            $buckets[match (true) {
                $days <= 30 => 'fresh',
                $days <= 90 => 'recent',
                $days <= 180 => 'stale',
                default => 'old',
            }]++;
        }

        return $buckets;
    }

    /**
     * Raw per-team breakdown rows (counts and percentages) for every team that
     * has at least one monitored server. The worst-in-column highlighting is
     * deliberately left to the caller — it depends on the dashboard's
     * absolute/percentage view toggle.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function teamRows(): Collection
    {
        $servers = $this->monitoredServers();
        $recentlyPatchedServerIds = $this->recentlyPatchedServerIds();

        return Team::query()
            ->orderBy('name')
            ->get()
            ->map(function (Team $team) use ($servers, $recentlyPatchedServerIds) {
                $teamServers = $servers->where('team_id', $team->id);
                $total = $teamServers->count();

                if ($total === 0) {
                    return null;
                }

                $overdue = $teamServers->filter(
                    fn (Server $server) => $server->isOverdue() && ! $server->isCurrentlySilenced()
                )->count();

                $silenced = $teamServers->filter(
                    fn (Server $server) => $server->isCurrentlySilenced()
                )->count();

                $patched = $teamServers->filter(
                    fn (Server $server) => in_array($server->id, $recentlyPatchedServerIds, true)
                )->count();

                return [
                    'team' => $team,
                    'total' => $total,
                    'overdue' => $overdue,
                    'silenced' => $silenced,
                    'patched_30d' => $patched,
                    'overdue_pct' => $this->pct($overdue, $total),
                    'silenced_pct' => $this->pct($silenced, $total),
                    'patched_30d_pct' => $this->pct($patched, $total),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Distinct ids of monitored servers patched in the last 30 days. Scoped to
     * the monitored set so the count (and the per-team breakdown) exclude
     * decommissioned and in-triage servers.
     *
     * @return array<int, int>
     */
    private function recentlyPatchedServerIds(): array
    {
        return $this->recentlyPatchedServerIds ??= PatchEvent::query()
            ->whereIn('server_id', $this->monitoredServers()->pluck('id')->all())
            ->where('patched_at', '>=', now()->subDays(30))
            ->distinct()
            ->pluck('server_id')
            ->all();
    }

    private function pct(int $part, int $whole): float
    {
        return $whole === 0 ? 0.0 : round($part / $whole * 100, 1);
    }
}
