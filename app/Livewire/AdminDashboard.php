<?php

namespace App\Livewire;

use App\Models\EstateSnapshot;
use App\Services\EstateStats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AdminDashboard extends Component
{
    #[Url(as: 'mode')]
    public $mode = 'percent';

    #[Url(as: 'range')]
    public $trendRange = 'year';

    public function render()
    {
        $stats = new EstateStats;
        $comparison = collect($this->comparisonSeries());

        return view('livewire.admin-dashboard', [
            'totalCount' => $stats->totalCount(),
            'overdueCount' => $stats->overdueCount(),
            'overdueSeverityBands' => $stats->overdueSeverityBands(),
            'silencedCount' => $stats->silencedCount(),
            'patchedRecentlyCount' => $stats->patchedRecentlyCount(),
            'neverCheckedInCount' => $stats->neverCheckedInCount(),
            'overdueServers' => $stats->overdueServers(),
            'teamRows' => $this->markWorstInColumn($stats->teamRows()),
            'postureBuckets' => $stats->postureBuckets(),
            'trendSeries' => $this->trendSeries(),
            'comparisonBars' => $comparison->whereNotNull('overdue_pct')->values()->all(),
            'comparisonMissing' => $comparison->whereNull('overdue_pct')->pluck('period')->all(),
            'postureSegments' => [
                ['key' => 'fresh', 'label' => '≤ 30 days', 'colour' => 'bg-green-500'],
                ['key' => 'recent', 'label' => '31–90 days', 'colour' => 'bg-lime-500'],
                ['key' => 'stale', 'label' => '91–180 days', 'colour' => 'bg-amber-500'],
                ['key' => 'old', 'label' => '180+ days', 'colour' => 'bg-red-500'],
                ['key' => 'never', 'label' => 'Never', 'colour' => 'bg-zinc-500'],
            ],
        ]);
    }

    /**
     * The overdue-percentage trend, oldest snapshot first, shaped for flux:chart.
     * Percentages (fractions 0–1) rather than raw counts, because the estate grows.
     *
     * @return array<int, array{date: string, overdue_pct: float}>
     */
    private function trendSeries(): array
    {
        $snapshots = EstateSnapshot::query()
            ->where('snapshot_date', '>=', $this->trendCutoff())
            ->orderBy('snapshot_date')
            ->get();

        return $this->thinForReadability($snapshots)
            ->map(fn (EstateSnapshot $snapshot) => [
                'date' => $snapshot->snapshot_date->format('Y-m-d'),
                'overdue_pct' => $snapshot->total > 0 ? round($snapshot->overdue / $snapshot->total, 4) : 0.0,
            ])
            ->all();
    }

    /**
     * The oldest snapshot date to show on the trend, based on the selected range.
     * The default covers any unexpected value, so a tampered URL just shows a year.
     */
    private function trendCutoff(): Carbon
    {
        return match ($this->trendRange) {
            'month' => today()->subMonthsNoOverflow(1),
            'quarter' => today()->subMonthsNoOverflow(3),
            '6months' => today()->subMonthsNoOverflow(6),
            default => today()->subYearsNoOverflow(1),
        };
    }

    /**
     * Drop intermediate snapshots so a long range reads as a digestible trend
     * rather than a daily jagged mess. Always keeps the first and last points.
     *
     * @param  Collection<int, EstateSnapshot>  $snapshots
     * @return Collection<int, EstateSnapshot>
     */
    private function thinForReadability(Collection $snapshots): Collection
    {
        $target = 30;
        $count = $snapshots->count();

        if ($count <= $target) {
            return $snapshots;
        }

        $stride = (int) ceil($count / $target);

        return $snapshots
            ->values()
            ->filter(fn (EstateSnapshot $snapshot, int $index) => $index % $stride === 0 || $index === $count - 1)
            ->values();
    }

    /**
     * Today's overdue % alongside the figures from ~1 month, ~1 quarter and ~1
     * year ago — each the nearest daily snapshot on or before that date, shaped
     * for flux:chart. overdue_pct is a fraction 0–1, or null when no snapshot
     * reaches back that far yet.
     *
     * @return array<int, array{period: string, overdue_pct: float|null}>
     */
    private function comparisonSeries(): array
    {
        $targets = [
            'Now' => today(),
            '1 month ago' => today()->subMonthsNoOverflow(1),
            '1 quarter ago' => today()->subMonthsNoOverflow(3),
            '1 year ago' => today()->subYearsNoOverflow(1),
        ];

        return collect($targets)
            ->map(fn (Carbon $date, string $period) => [
                'period' => $period,
                'overdue_pct' => $this->overduePctOnOrBefore($date),
            ])
            ->values()
            ->all();
    }

    private function overduePctOnOrBefore(Carbon $date): ?float
    {
        $snapshot = EstateSnapshot::query()
            ->where('snapshot_date', '<=', $date)
            ->orderByDesc('snapshot_date')
            ->first();

        if ($snapshot === null) {
            return null;
        }

        return $snapshot->total > 0 ? round($snapshot->overdue / $snapshot->total, 4) : 0.0;
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
}
