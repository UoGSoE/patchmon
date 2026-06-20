<?php

namespace App\Mail;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UnassignedServersDigest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Server>  $servers
     */
    public function __construct(public Collection $servers) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Patchmon: servers awaiting team allocation',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.unassigned-servers-digest',
            with: [
                'servers' => $this->servers,
            ],
        );
    }
}
