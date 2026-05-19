<div class="max-w-3xl">
    <flux:button :href="route('admin.teams.index')" icon="arrow-left" size="sm" wire:navigate>Back to teams</flux:button>

    <div class="mt-4 flex items-center gap-3">
        <flux:heading size="xl">{{ $team->name }}</flux:heading>
        @if ($team->isCurrentlySilenced())
            <flux:badge color="zinc">Silenced until {{ $team->silenced_until->format('D j M, H:i') }}</flux:badge>
        @endif
    </div>

    <flux:text class="mt-1">{{ $team->notification_email }}</flux:text>

    <div class="mt-6">
        <flux:heading size="sm">Silencing</flux:heading>
        <flux:text size="sm">Silencing the team silences every job it owns.</flux:text>
        <div class="mt-2">
            @if ($team->isCurrentlySilenced())
                <flux:button wire:click="unsilence" icon="speaker-wave">Unsilence team</flux:button>
            @else
                <flux:modal.trigger name="silence-team">
                    <flux:button icon="speaker-x-mark">Silence team…</flux:button>
                </flux:modal.trigger>
            @endif
        </div>
    </div>

    <div class="mt-8">
        <flux:heading size="sm">Members</flux:heading>

        <form wire:submit="addUser" class="mt-3 flex gap-3">
            <flux:select wire:model="userToAddId" class="flex-1">
                <flux:select.option value="">Add a user…</flux:select.option>
                @foreach ($candidates as $candidate)
                    <flux:select.option value="{{ $candidate->id }}">{{ $candidate->full_name ?: $candidate->email }} ({{ $candidate->email }})</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary">Add</flux:button>
        </form>

        @if ($members->isEmpty())
            <flux:text class="mt-3">No members yet.</flux:text>
        @else
            <flux:table class="mt-3">
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Email</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($members as $member)
                        <flux:table.row wire:key="team-member-row-{{ $member->id }}">
                            <flux:table.cell>{{ $member->full_name }}</flux:table.cell>
                            <flux:table.cell>{{ $member->email }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:button
                                    wire:click="removeUser({{ $member->id }})"
                                    wire:confirm="Remove {{ $member->full_name ?: $member->email }} from the team?"
                                    size="sm"
                                >Remove</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <flux:modal name="silence-team" variant="flyout">
        <form wire:submit="silence" class="space-y-6">
            <flux:heading size="lg">Silence this team</flux:heading>
            <flux:text>Cronmon won't email anyone about jobs owned by this team until the time you pick.</flux:text>

            <flux:input wire:model="silenceUntil" type="datetime-local" label="Silenced until" />
            <flux:input wire:model="silenceReason" label="Reason (optional)" placeholder="Building works" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" x-on:click="$flux.modal('silence-team').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Silence</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
