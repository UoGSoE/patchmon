<div class="max-w-5xl">
    <flux:heading size="xl">API tokens</flux:heading>
    <flux:text class="mt-2">Every API token across every user. Revoke any that look out of place.</flux:text>

    @if ($tokens->isEmpty())
        <flux:text class="mt-6" size="sm">No API tokens have been created yet.</flux:text>
    @else
        <flux:table class="mt-6">
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Owner</flux:table.column>
                <flux:table.column>Abilities</flux:table.column>
                <flux:table.column>Last used</flux:table.column>
                <flux:table.column>Created</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($tokens as $token)
                    <flux:table.row wire:key="token-row-{{ $token->id }}">
                        <flux:table.cell>{{ $token->name }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($token->tokenable)
                                {{ $token->tokenable->full_name ?: $token->tokenable->email }}
                                <flux:text size="sm" class="block">{{ $token->tokenable->email }}</flux:text>
                            @else
                                <flux:text size="sm">(deleted user)</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ implode(', ', $token->abilities ?? []) }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'Never' }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $token->created_at->diffForHumans() }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button
                                wire:click="confirmRevokeToken({{ $token->id }})"
                                size="sm"
                                icon="trash"
                                tooltip="Revoke"
                                variant="danger"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="admin-revoke-api-token" class="max-w-sm">
        @if ($revokingTokenId)
            <div class="space-y-4">
                <flux:heading size="lg">Revoke '{{ $revokingTokenName }}'?</flux:heading>
                <flux:text>This will stop working immediately for {{ $revokingTokenOwner }}.</flux:text>
                <div class="flex justify-end gap-2">
                    <flux:button type="button" x-on:click="$flux.modal('admin-revoke-api-token').close()">Cancel</flux:button>
                    <flux:button wire:click="revokeToken" variant="danger">Revoke</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
