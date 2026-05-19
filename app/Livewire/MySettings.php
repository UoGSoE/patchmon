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

    public string $silenceUntil = '';

    public ?string $silenceReason = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->notificationEmail = $user->notification_email;
        $this->senderEmail = $user->sender_email;
        $this->silenceUntil = now()->addDay()->format('Y-m-d\TH:i');
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

    public function silence(): void
    {
        $this->validate([
            'silenceUntil' => ['required', 'date', 'after:now'],
            'silenceReason' => ['nullable', 'string', 'max:255'],
        ]);

        auth()->user()->silenceUntil(Carbon::parse($this->silenceUntil), $this->silenceReason);

        Flux::modal('silence-self')->close();
        Flux::toast('All your jobs are silenced.', variant: 'success');
    }

    public function unsilence(): void
    {
        auth()->user()->unsilence();

        Flux::toast('You are unsilenced.', variant: 'success');
    }

    public function render()
    {
        return view('livewire.my-settings', [
            'user' => auth()->user()->fresh(),
        ]);
    }
}
