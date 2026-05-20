<?php

namespace App\Livewire;

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
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

    public string $tokenName = '';

    /** @var array<int, string> */
    public array $tokenAbilities = ['jobs:read', 'jobs:write'];

    public ?string $lastCreatedToken = null;

    public ?int $revokingTokenId = null;

    public ?string $revokingTokenName = null;

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

    public function openCreateToken(): void
    {
        $this->tokenName = '';
        $this->tokenAbilities = ['jobs:read', 'jobs:write'];
        $this->lastCreatedToken = null;
        $this->resetErrorBag();

        Flux::modal('create-api-token')->show();
    }

    public function createToken(): void
    {
        $this->validate([
            'tokenName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('personal_access_tokens', 'name')->where(fn ($q) => $q
                    ->where('tokenable_id', auth()->id())
                    ->where('tokenable_type', User::class)),
            ],
            'tokenAbilities' => ['required', 'array', 'min:1'],
            'tokenAbilities.*' => ['in:jobs:read,jobs:write'],
        ]);

        $token = auth()->user()->createToken($this->tokenName, $this->tokenAbilities);

        $this->lastCreatedToken = $token->plainTextToken;
    }

    public function confirmRevokeToken(int $id): void
    {
        $token = auth()->user()->tokens()->findOrFail($id);

        $this->revokingTokenId = $token->id;
        $this->revokingTokenName = $token->name;

        Flux::modal('revoke-api-token')->show();
    }

    public function revokeToken(): void
    {
        if (! $this->revokingTokenId) {
            return;
        }

        auth()->user()->tokens()->where('id', $this->revokingTokenId)->delete();

        Flux::modal('revoke-api-token')->close();
        Flux::toast("Token '{$this->revokingTokenName}' revoked.", variant: 'success');

        $this->revokingTokenId = null;
        $this->revokingTokenName = null;
    }

    public function render()
    {
        return view('livewire.my-settings', [
            'user' => auth()->user()->fresh(),
            'apiTokens' => auth()->user()->tokens()->latest()->get(),
        ]);
    }
}
