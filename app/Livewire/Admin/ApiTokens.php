<?php

namespace App\Livewire\Admin;

use App\Events\ActivityOccurred;
use App\Models\User;
use Flux\Flux;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ApiTokens extends Component
{
    public ?int $revokingTokenId = null;

    public ?string $revokingTokenName = null;

    public ?string $revokingTokenOwner = null;

    public function confirmRevokeToken(int $id): void
    {
        $token = PersonalAccessToken::findOrFail($id);
        $owner = User::find($token->tokenable_id);

        $this->revokingTokenId = $token->id;
        $this->revokingTokenName = $token->name;
        $this->revokingTokenOwner = $owner ? ($owner->full_name ?: $owner->email) : 'unknown user';

        Flux::modal('admin-revoke-api-token')->show();
    }

    public function revokeToken(): void
    {
        if (! $this->revokingTokenId) {
            return;
        }

        PersonalAccessToken::where('id', $this->revokingTokenId)->delete();

        ActivityOccurred::dispatch(
            auth()->id(),
            null,
            "Revoked {$this->revokingTokenOwner}'s API token '{$this->revokingTokenName}'",
            request()->ip(),
        );

        Flux::modal('admin-revoke-api-token')->close();
        Flux::toast("Token '{$this->revokingTokenName}' revoked.", variant: 'success');

        $this->revokingTokenId = null;
        $this->revokingTokenName = null;
        $this->revokingTokenOwner = null;
    }

    public function render()
    {
        $tokens = PersonalAccessToken::query()
            ->with('tokenable')
            ->latest()
            ->get();

        return view('livewire.admin.api-tokens', [
            'tokens' => $tokens,
        ]);
    }
}
