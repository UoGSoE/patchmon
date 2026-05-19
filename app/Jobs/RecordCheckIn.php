<?php

namespace App\Jobs;

use App\Models\Job as MonitoredJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class RecordCheckIn implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $jobId,
        public ?string $sourceIp,
        public Carbon $at,
    ) {}

    public function handle(): void
    {
        $job = MonitoredJob::find($this->jobId);

        if (! $job) {
            return;
        }

        $job->recordCheckIn($this->sourceIp, $this->at);
    }
}
