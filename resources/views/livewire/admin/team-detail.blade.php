<div class="max-w-3xl">
    <flux:heading size="xl">{{ $team->name }}</flux:heading>

    <flux:text class="mt-1">{{ $team->notification_email }}</flux:text>

    <div class="mt-8">
        <flux:heading size="sm">Members</flux:heading>

        <form wire:submit="addUser" class="mt-3 flex gap-3">
            <flux:select wire:model="userToAddId" variant="listbox" searchable placeholder="Add a user…" class="flex-1">
                @foreach ($candidates as $candidate)
                    <flux:select.option :value="$candidate->id">{{ $candidate->full_name ?: $candidate->email }} ({{ $candidate->email }})</flux:select.option>
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
                            <flux:table.cell align="end">
                                <flux:button
                                    wire:click="confirmRemoveUser({{ $member->id }})"
                                    size="sm"
                                    icon="x-mark"
                                    tooltip="Remove from team"
                                    variant="danger"
                                />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <flux:modal name="remove-member" variant="flyout" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">Remove from team?</flux:heading>
            <flux:text>
                Remove <strong>{{ $removingMemberName }}</strong> from this team?
                They keep their account and can be added back later.
            </flux:text>
            <div class="flex justify-end gap-2">
                <flux:button type="button" x-on:click="$flux.modal('remove-member').close()">Cancel</flux:button>
                <flux:button wire:click="removeUser({{ $removingMemberId }})" variant="danger">Remove</flux:button>
            </div>
        </div>
    </flux:modal>

</div>
