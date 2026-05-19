<?php

namespace App\Livewire;

use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MySettings extends Component
{
    public ?string $notificationEmail = null;

    public ?string $senderEmail = null;

    public bool $silenced = false;

    public string $silenceUntil = '';

    public ?string $silenceReason = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->notificationEmail = $user->notification_email;
        $this->senderEmail = $user->sender_email;
        $this->silenced = $user->isCurrentlySilenced();
        $this->silenceUntil = $user->silenced_until
            ? $user->silenced_until->format('Y-m-d\TH:i')
            : now()->addDay()->format('Y-m-d\TH:i');
        $this->silenceReason = $user->silence_reason;
    }

    public function saveEmails(): void
    {
        $this->validate([
            'notificationEmail' => ['nullable', 'email'],
            'senderEmail' => ['nullable', 'email'],
        ]);

        auth()->user()->update([
            'notification_email' => $this->notificationEmail,
            'sender_email' => $this->senderEmail,
        ]);

        Flux::toast('Email preferences saved.', variant: 'success');
    }

    public function updatedSilenced(bool $value): void
    {
        if ($value) {
            $this->validate(['silenceUntil' => ['required', 'date', 'after:now']]);
            auth()->user()->silenceUntil(Carbon::parse($this->silenceUntil), $this->silenceReason);
            Flux::toast('All your jobs are silenced.', variant: 'success');
        } else {
            auth()->user()->unsilence();
            $this->silenceReason = null;
            $this->silenceUntil = now()->addDay()->format('Y-m-d\TH:i');
            Flux::toast('You are unsilenced.', variant: 'success');
        }
    }

    public function updatedSilenceUntil(): void
    {
        if (! $this->silenced) {
            return;
        }
        $this->validate(['silenceUntil' => ['required', 'date', 'after:now']]);
        auth()->user()->silenceUntil(Carbon::parse($this->silenceUntil), $this->silenceReason);
    }

    public function updatedSilenceReason(): void
    {
        if (! $this->silenced) {
            return;
        }
        $this->validate(['silenceReason' => ['nullable', 'string', 'max:255']]);
        auth()->user()->silenceUntil(Carbon::parse($this->silenceUntil), $this->silenceReason);
    }

    public function render()
    {
        return view('livewire.my-settings', [
            'user' => auth()->user()->fresh(),
        ]);
    }
}
