<?php

namespace App\Mail;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ServerAwolNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Server $server) {}

    public function envelope(): Envelope
    {
        $sender = $this->server->resolveSenderEmail();

        return new Envelope(
            subject: "Patchmon: {$this->server->name} hasn't been patched",
            from: $sender ? new Address($sender) : null,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.server-awol',
            with: [
                'server' => $this->server,
            ],
        );
    }
}
