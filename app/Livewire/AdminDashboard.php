<?php

namespace App\Livewire;

use App\Services\EstateStats;
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
        $stats = new EstateStats;

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
            'postureSegments' => [
                ['key' => 'fresh', 'label' => '≤ 30 days', 'colour' => 'bg-green-500'],
                ['key' => 'recent', 'label' => '31–90 days', 'colour' => 'bg-lime-500'],
                ['key' => 'stale', 'label' => '91–180 days', 'colour' => 'bg-amber-500'],
                ['key' => 'old', 'label' => '180+ days', 'colour' => 'bg-red-500'],
                ['key' => 'never', 'label' => 'Never', 'colour' => 'bg-zinc-500'],
            ],
        ]);
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
