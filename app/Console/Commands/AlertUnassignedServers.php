<?php

namespace App\Console\Commands;

use App\Mail\UnassignedServersDigest;
use App\Models\Server;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('patchmon:alert-unassigned')]
#[Description('Email the oversight admins about servers that have sat unassigned (in triage) for over a week.')]
class AlertUnassignedServers extends Command
{
    public function handle(): int
    {
        $servers = Server::query()
            ->whereNull('team_id')
            ->where('created_at', '<=', now()->subWeek())
            ->orderBy('created_at')
            ->get();

        if ($servers->isEmpty()) {
            return self::SUCCESS;
        }

        $oversightAdmins = User::oversightAdmins()->get();

        if ($oversightAdmins->isEmpty()) {
            return self::SUCCESS;
        }

        Mail::to($oversightAdmins)->queue(new UnassignedServersDigest($servers));

        return self::SUCCESS;
    }
}
