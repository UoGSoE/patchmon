<?php

namespace App\Console\Commands;

use App\Mail\ServerAwolNotification;
use App\Models\Server;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('patchmon:evaluate')]
#[Description('Evaluate every server and send alert emails for those that are overdue.')]
class PatchmonEvaluate extends Command
{
    public function handle(): int
    {
        Server::query()->each(function (Server $server): void {
            if ($server->isCurrentlySilenced()) {
                return;
            }

            if (! $server->isOverdue()) {
                return;
            }

            if ($server->alerting_since === null) {
                $server->alerting_since = now();
                $this->dispatchAlert($server);

                return;
            }

            $nextDue = $server->nextScheduledAfter($server->last_alerted_at)->addMinutes($server->graceMinutes());

            if (now()->greaterThanOrEqualTo($nextDue)) {
                $this->dispatchAlert($server);
            }
        });

        return self::SUCCESS;
    }

    private function dispatchAlert(Server $server): void
    {
        Mail::to($server->resolveNotificationEmail())->queue(new ServerAwolNotification($server));

        $server->last_alerted_at = now();
        $server->save();
    }
}
