<div class="max-w-2xl">
    <div class="flex flex-col md:flex-row items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">My settings</flux:heading>
            <flux:text class="mt-2">Manage API tokens you use to script against Patchmon. <flux:link :href="route('api.help')">Examples &amp; docs.</flux:link></flux:text>
        </div>
        <div>
            <flux:button wire:click="openCreateToken" icon="plus" variant="primary">New token</flux:button>
        </div>
    </div>

    <div class="mt-10">
        @if ($apiTokens->isEmpty())
            <flux:text class="mt-4" size="sm">You haven't created any API tokens yet.</flux:text>
        @else
            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Abilities</flux:table.column>
                    <flux:table.column>Last used</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($apiTokens as $token)
                        <flux:table.row wire:key="token-row-{{ $token->id }}">
                            <flux:table.cell>{{ $token->name }}</flux:table.cell>
                            <flux:table.cell>{{ implode(', ', $token->abilities ?? []) }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'Never' }}
                            </flux:table.cell>
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
    </div>

    <flux:modal name="create-api-token" variant="flyout" class="max-w-md">
        @if ($lastCreatedToken)
            <div class="space-y-4">
                <flux:heading size="lg">Token created</flux:heading>
                <flux:callout icon="exclamation-triangle" variant="warning">
                    This is the only time you'll see this token. Save it now.
                </flux:callout>
                <flux:input readonly copyable :value="$lastCreatedToken" />
                <div class="flex justify-end">
                    <flux:button type="button" x-on:click="$flux.modal('create-api-token').close()">Done</flux:button>
                </div>
            </div>
        @else
            <form wire:submit="createToken" class="space-y-6">
                <flux:heading size="lg">New API token</flux:heading>
                <flux:input wire:model="tokenName" label="Name" description="What's this token for? e.g. backup-host-01" required />
                <flux:checkbox.group wire:model="tokenAbilities" label="Abilities">
                    <flux:checkbox value="servers:read" label="Read servers and patch events" />
                    <flux:checkbox value="servers:write" label="Create, update, silence and delete servers" />
                </flux:checkbox.group>
                <div class="flex justify-end gap-2">
                    <flux:button type="button" x-on:click="$flux.modal('create-api-token').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Create token</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    <flux:modal name="revoke-api-token" class="max-w-sm">
        @if ($revokingTokenId)
            <div class="space-y-4">
                <flux:heading size="lg">Revoke '{{ $revokingTokenName }}'?</flux:heading>
                <flux:text>It will stop working immediately. Anything using it will need a new token.</flux:text>
                <div class="flex justify-end gap-2">
                    <flux:button type="button" x-on:click="$flux.modal('revoke-api-token').close()">Cancel</flux:button>
                    <flux:button wire:click="revokeToken" variant="danger">Revoke</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
