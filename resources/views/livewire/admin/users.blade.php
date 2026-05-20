<div class="max-w-4xl">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Users</flux:heading>
            <flux:text class="mt-2">Promote / demote admins, or add a new user to the SSO allowlist.</flux:text>
        </div>
        <flux:button wire:click="openCreate" icon="plus" variant="primary">New user</flux:button>
    </div>

    <flux:table class="mt-6">
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Admin</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($users as $user)
                <flux:table.row wire:key="user-row-{{ $user->id }}">
                    <flux:table.cell>{{ $user->full_name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($user->is(auth()->user()))
                            <flux:switch :checked="$user->is_admin" disabled />
                        @else
                            <flux:switch :checked="$user->is_admin" wire:click="toggleAdmin({{ $user->id }})" />
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-1">
                            <flux:button
                                wire:click="openEdit({{ $user->id }})"
                                size="sm"
                                icon="pencil-square"
                                tooltip="Edit"
                            />
                            @unless ($user->is(auth()->user()))
                                <flux:button
                                    wire:click="confirmDelete({{ $user->id }})"
                                    size="sm"
                                    icon="trash"
                                    tooltip="Delete"
                                    variant="danger"
                                />
                            @endunless
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="user-form" variant="flyout" class="max-w-md">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingUserId ? 'Edit user' : 'New user' }}</flux:heading>
            <flux:input
                wire:model="form.username"
                label="Username"
                description="SSO username, e.g. kmc2y"
                required
            />
            <flux:input wire:model="form.forenames" label="Forenames" required />
            <flux:input wire:model="form.surname" label="Surname" required />
            <flux:input wire:model="form.email" type="email" label="Email" required />
            <flux:text size="sm">Admin status is set via the toggle on the row, not this form.</flux:text>
            <div class="flex justify-end gap-2">
                <flux:button type="button" x-on:click="$flux.modal('user-form').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-user" variant="flyout" class="max-w-lg">
        @if ($deletingUserId)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Delete {{ $deletingUserName }}</flux:heading>
                    @if ($deletingUserPersonalJobCount === 0)
                        <flux:text class="mt-2">This user owns no personal jobs. Their team memberships will be removed.</flux:text>
                    @else
                        <flux:text class="mt-2">
                            This user owns {{ $deletingUserPersonalJobCount }} personal {{ \Illuminate\Support\Str::plural('job', $deletingUserPersonalJobCount) }}.
                            Choose what happens to them before the user can be deleted.
                        </flux:text>
                    @endif
                </div>

                @if ($deletingUserPersonalJobCount > 0)
                    <form wire:submit="transferAndDelete" class="space-y-3">
                        <flux:heading size="md">Transfer personal jobs to another user</flux:heading>
                        <flux:select wire:model="transferTargetUserId" variant="listbox" searchable placeholder="Choose a user…">
                            @foreach ($transferCandidates as $candidate)
                                <flux:select.option :value="$candidate->id">{{ $candidate->full_name ?: $candidate->email }} ({{ $candidate->email }})</flux:select.option>
                            @endforeach
                        </flux:select>
                        <div class="flex justify-end">
                            <flux:button type="submit">Transfer and delete</flux:button>
                        </div>
                    </form>
                @endif

                <form wire:submit="deleteWithJobs" class="space-y-3">
                    <flux:heading size="md">
                        @if ($deletingUserPersonalJobCount > 0)
                            Or delete the user and all their personal jobs
                        @else
                            Confirm delete
                        @endif
                    </flux:heading>
                    <flux:text>Type <strong>{{ $deletingUserName }}</strong> to confirm.</flux:text>
                    <flux:input wire:model="typedConfirmation" placeholder="{{ $deletingUserName }}" />
                    <div class="flex justify-end gap-2">
                        <flux:button type="button" x-on:click="$flux.modal('delete-user').close()">Cancel</flux:button>
                        <flux:button type="submit" variant="danger">Delete</flux:button>
                    </div>
                </form>
            </div>
        @endif
    </flux:modal>
</div>
