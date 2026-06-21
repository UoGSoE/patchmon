<?php

namespace App\Mail;

use App\Services\EstateStats;
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
        // The monitored estate, defined exactly as the dashboard and evaluator see it,
        // via the shared EstateStats service (see ADR patchmon-uqwCr).
        $stats = new EstateStats;

        $overdueServers = $stats->overdueServers();

        return new Content(
            markdown: 'emails.weekly-overview',
            with: [
                'totalCount' => $stats->totalCount(),
                'overdueServers' => $overdueServers,
                'overdueCount' => $overdueServers->count(),
                'silencedCount' => $stats->silencedCount(),
                'patchedRecentlyCount' => $stats->patchedRecentlyCount(),
            ],
        );
    }
}
