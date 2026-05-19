<?php

namespace App\Console\Commands;

use App\Mail\JobAwolNotification;
use App\Models\Job;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('cronmon:evaluate')]
#[Description('Evaluate every monitored job and send alert emails for those that are overdue.')]
class CronmonEvaluate extends Command
{
    public function handle(): int
    {
        Job::query()->each(function (Job $job): void {
            if ($job->isCurrentlySilenced()) {
                return;
            }

            if (! $job->isOverdue()) {
                return;
            }

            if ($job->alerting_since === null) {
                $job->alerting_since = now();
                $this->dispatchAlert($job);

                return;
            }

            $nextDue = $job->nextScheduledAfter($job->last_alerted_at)->addMinutes($job->graceMinutes());

            if (now()->greaterThanOrEqualTo($nextDue)) {
                $this->dispatchAlert($job);
            }
        });

        return self::SUCCESS;
    }

    private function dispatchAlert(Job $job): void
    {
        Mail::to($job->resolveNotificationEmail())->queue(new JobAwolNotification($job));

        $job->last_alerted_at = now();
        $job->save();
    }
}
