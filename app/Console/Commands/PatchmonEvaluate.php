<?php

namespace App\Console\Commands;

use App\Events\ActivityOccurred;
use App\Mail\ServerOverdueNotification;
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
            if ($server->isUnassigned()) {
                return;
            }

            if ($server->isInactive()) {
                return;
            }

            if ($server->isCurrentlySilenced()) {
                return;
            }

            if (! $server->isOverdue()) {
                return;
            }

            if ($server->isntAlerting()) {
                $server->markAsAlerting();
                $server->sendAlert();
                ActivityOccurred::dispatch(null, $server->id, 'Server became overdue');

                return;
            }

            if ($server->shouldSendAnotherAlert()) {
                $server->sendAlert();
                ActivityOccurred::dispatch(null, $server->id, 'Overdue alert re-sent');
            }
        });

        return self::SUCCESS;
    }
}
