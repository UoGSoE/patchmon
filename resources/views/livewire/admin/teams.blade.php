<div class="max-w-4xl">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Teams</flux:heading>
            <flux:text class="mt-2">Create teams, manage who's in them, edit team defaults.</flux:text>
        </div>
        <flux:button wire:click="openCreate" variant="primary" icon="plus">New team</flux:button>
    </div>

    @if ($teams->isEmpty())
        <flux:text class="mt-6">No teams yet.</flux:text>
    @else
        <flux:table class="mt-6">
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Notification email</flux:table.column>
                <flux:table.column>Members</flux:table.column>
                <flux:table.column>Jobs</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($teams as $team)
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:link :href="route('admin.teams.show', $team)" wire:navigate>{{ $team->name }}</flux:link>
                            @if ($team->isCurrentlySilenced())
                                <flux:badge color="zinc" size="sm" class="ml-2">Silenced</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $team->notification_email }}</flux:table.cell>
                        <flux:table.cell>{{ $team->users()->count() }}</flux:table.cell>
                        <flux:table.cell>{{ $team->jobs()->count() }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-2">
                                <flux:button wire:click="openEdit({{ $team->id }})" size="sm">Edit</flux:button>
                                <flux:button
                                    wire:click="delete({{ $team->id }})"
                                    wire:confirm="Delete {{ $team->name }}? This removes the team and all its jobs."
                                    size="sm"
                                    variant="danger"
                                >Delete</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="edit-team" class="md:w-96">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editing['id'] ? 'Edit team' : 'New team' }}</flux:heading>
            <flux:input wire:model="editing.name" label="Name" required />
            <flux:input wire:model="editing.notification_email" type="email" label="Notification email" required />
            <flux:input wire:model="editing.sender_email" type="email" label="Sender email (optional)" />
            <div class="flex justify-end gap-3">
                <flux:button type="button" x-on:click="$flux.modal('edit-team').close()" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
