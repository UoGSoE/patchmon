<?php

namespace App\Mail;

use App\Models\PatchEvent;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyOverview extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Patchmon: weekly patching overview',
        );
    }

    public function content(): Content
    {
        // The monitored estate, defined exactly as the dashboard and evaluator see it:
        // live (not decommissioned) servers that belong to a team.
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

        $patchedRecentlyCount = PatchEvent::query()
            ->where('patched_at', '>=', now()->subDays(30))
            ->distinct()
            ->count('server_id');

        return new Content(
            markdown: 'emails.weekly-overview',
            with: [
                'totalCount' => $servers->count(),
                'overdueServers' => $overdueServers,
                'overdueCount' => $overdueServers->count(),
                'silencedCount' => $silencedCount,
                'patchedRecentlyCount' => $patchedRecentlyCount,
            ],
        );
    }
}
