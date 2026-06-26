<?php

namespace App\Livewire;

use App\Events\ActivityOccurred;
use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MySettings extends Component
{
    public string $tokenName = '';

    /** @var array<int, string> */
    public array $tokenAbilities = ['servers:read', 'servers:write'];

    public ?string $lastCreatedToken = null;

    public ?int $revokingTokenId = null;

    public ?string $revokingTokenName = null;

    public function openCreateToken(): void
    {
        $this->tokenName = '';
        $this->tokenAbilities = ['servers:read', 'servers:write'];
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
            'tokenAbilities.*' => ['in:servers:read,servers:write'],
        ]);

        $token = auth()->user()->createToken($this->tokenName, $this->tokenAbilities);

        ActivityOccurred::dispatch(auth()->id(), null, "Created the API token '{$this->tokenName}'", request()->ip());

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

        ActivityOccurred::dispatch(auth()->id(), null, "Revoked the API token '{$this->revokingTokenName}'", request()->ip());

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
