<?php

namespace App\Mail;

use App\Models\Job;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JobAwolNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Job $job) {}

    public function envelope(): Envelope
    {
        $sender = $this->job->resolveSenderEmail();

        return new Envelope(
            subject: "Cronmon: {$this->job->name} hasn't checked in",
            from: $sender ? new Address($sender) : null,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.job-awol',
            with: [
                'job' => $this->job,
            ],
        );
    }
}
