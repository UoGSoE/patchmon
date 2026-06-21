<?php

namespace App\Livewire;

use App\Models\PatchEvent;
use App\Models\Server;
use App\Models\Team;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AdminDashboard extends Component
{
    #[Url(as: 'mode')]
    public $mode = 'percent';

    public function render()
    {
        // The dashboard reflects the live, monitored estate — the same servers the
        // evaluator alerts on. Decommissioned (inactive) and in-triage (no team)
        // servers are deliberately skipped by the evaluator, so they're excluded here too.
        $servers = Server::query()
            ->whereNull('inactive_since')
            ->whereNotNull('team_id')
            ->with('team')
            ->get();

        $overdueServers = $servers
            ->filter(fn (Server $server) => $server->isOverdue() && ! $server->isCurrentlySilenced())
            ->sortBy(fn (Server $server) => $server->deadline()->timestamp)
            ->values();

        $silencedCount = $servers->filter(
            fn (Server $server) => $server->isCurrentlySilenced()
        )->count();

        $recentlyPatchedServerIds = PatchEvent::query()
            ->where('patched_at', '>=', now()->subDays(30))
            ->distinct()
            ->pluck('server_id')
            ->all();

        return view('livewire.admin-dashboard', [
            'totalCount' => $servers->count(),
            'overdueCount' => $overdueServers->count(),
            'silencedCount' => $silencedCount,
            'patchedRecentlyCount' => count($recentlyPatchedServerIds),
            'neverCheckedInCount' => Server::neverCheckedIn()->count(),
            'overdueServers' => $overdueServers,
            'teamRows' => $this->buildTeamRows($servers, $recentlyPatchedServerIds),
            'postureBuckets' => $this->buildPostureBuckets($servers),
            'postureSegments' => [
                ['key' => 'fresh', 'label' => '≤ 30 days', 'colour' => 'bg-green-500'],
                ['key' => 'recent', 'label' => '31–90 days', 'colour' => 'bg-lime-500'],
                ['key' => 'stale', 'label' => '91–180 days', 'colour' => 'bg-amber-500'],
                ['key' => 'old', 'label' => '180+ days', 'colour' => 'bg-red-500'],
                ['key' => 'never', 'label' => 'Never', 'colour' => 'bg-zinc-500'],
            ],
        ]);
    }

    private function buildPostureBuckets(Collection $servers): array
    {
        $buckets = ['fresh' => 0, 'recent' => 0, 'stale' => 0, 'old' => 0, 'never' => 0];

        foreach ($servers as $server) {
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

    private function buildTeamRows(Collection $servers, array $recentlyPatchedServerIds): Collection
    {
        $rows = Team::query()
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

        return $this->markWorstInColumn($rows);
    }

    private function markWorstInColumn(Collection $rows): Collection
    {
        $columns = [
            'overdue' => 'max',
            'silenced' => 'max',
            'patched_30d' => 'min',
        ];

        $worstValues = [];
        foreach ($columns as $column => $direction) {
            $key = $this->mode === 'percent' ? "{$column}_pct" : $column;
            $values = $rows->pluck($key);

            if ($values->unique()->count() <= 1) {
                $worstValues[$column] = null;

                continue;
            }

            $worstValues[$column] = $direction === 'max' ? $values->max() : $values->min();
        }

        return $rows->map(function (array $row) use ($worstValues) {
            foreach ($worstValues as $column => $worst) {
                $key = $this->mode === 'percent' ? "{$column}_pct" : $column;
                $row["{$column}_is_worst"] = $worst !== null && $row[$key] === $worst;
            }

            return $row;
        });
    }

    private function pct(int $part, int $whole): float
    {
        return $whole === 0 ? 0.0 : round($part / $whole * 100, 1);
    }
}
