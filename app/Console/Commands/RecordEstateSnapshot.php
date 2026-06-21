<?php

namespace App\Console\Commands;

use App\Models\EstateSnapshot;
use App\Services\EstateStats;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('patchmon:snapshot')]
#[Description('Record a daily snapshot of the estate posture for the in-app trend charts.')]
class RecordEstateSnapshot extends Command
{
    public function handle(): int
    {
        $stats = new EstateStats;

        // One row per day; re-running replaces the day's figures rather than duplicating.
        EstateSnapshot::updateOrCreate(
            ['snapshot_date' => today()],
            [
                'total' => $stats->totalCount(),
                'overdue' => $stats->overdueCount(),
                'silenced' => $stats->silencedCount(),
                'patched_30d' => $stats->patchedRecentlyCount(),
                'never_checked_in' => $stats->neverCheckedInCount(),
            ],
        );

        return self::SUCCESS;
    }
}
