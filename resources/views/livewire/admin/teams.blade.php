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
                    <flux:table.row wire:key="team-row-{{ $team->id }}">
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
                                    wire:click="confirmDelete({{ $team->id }})"
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

    <flux:modal name="delete-team" variant="flyout">
        @if ($deletingTeam)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Delete {{ $deletingTeam->name }}</flux:heading>
                    @if ($deletingTeam->jobs->isEmpty())
                        <flux:text class="mt-2">This team owns no jobs.</flux:text>
                    @else
                        <flux:text class="mt-2">
                            This team owns {{ $deletingTeam->jobs->count() }} {{ \Illuminate\Support\Str::plural('job', $deletingTeam->jobs->count()) }}.
                            Choose what should happen to them before the team can be deleted.
                        </flux:text>
                        <ul class="mt-3 list-disc list-inside text-sm">
                            @foreach ($deletingTeam->jobs->take(5) as $job)
                                <li>{{ $job->name }}</li>
                            @endforeach
                            @if ($deletingTeam->jobs->count() > 5)
                                <li>… and {{ $deletingTeam->jobs->count() - 5 }} more</li>
                            @endif
                        </ul>
                    @endif
                </div>

                @if ($deletingTeam->jobs->isEmpty())
                    <div class="flex gap-2">
                        <flux:spacer />
                        <flux:button x-on:click="$flux.modal('delete-team').close()">Cancel</flux:button>
                        <flux:button wire:click="deleteEmpty" variant="danger">Delete team</flux:button>
                    </div>
                @else
                    <form wire:submit="transferToTeamAndDelete" class="space-y-3">
                        <flux:heading size="md">Transfer jobs to another team</flux:heading>
                        <flux:select wire:model="transferTargetTeamId" variant="listbox" searchable placeholder="Choose a team…">
                            @foreach ($otherTeams as $candidate)
                                <flux:select.option :value="$candidate->id">{{ $candidate->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit">Transfer and delete</flux:button>
                        </div>
                    </form>

                    <form wire:submit="transferToUserAndDelete" class="space-y-3">
                        <flux:heading size="md">Transfer jobs to a user</flux:heading>
                        <flux:select wire:model="transferTargetUserId" variant="listbox" searchable placeholder="Choose a user…">
                            @foreach ($allUsers as $candidate)
                                <flux:select.option :value="$candidate->id">{{ $candidate->full_name }} ({{ $candidate->email }})</flux:select.option>
                            @endforeach
                        </flux:select>
                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit">Transfer and delete</flux:button>
                        </div>
                    </form>

                    <form wire:submit="deleteWithJobs" class="space-y-3">
                        <flux:heading size="md">Delete the team and all its jobs</flux:heading>
                        <flux:text>Type <strong>{{ $deletingTeam->name }}</strong> to confirm.</flux:text>
                        <flux:input wire:model="typedConfirmation" placeholder="{{ $deletingTeam->name }}" />
                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit" variant="danger">Delete everything</flux:button>
                        </div>
                    </form>
                @endif
            </div>
        @endif
    </flux:modal>

    <flux:modal name="edit-team" variant="flyout">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editing['id'] ? 'Edit team' : 'New team' }}</flux:heading>
            <flux:input wire:model="editing.name" label="Name" required />
            <flux:input wire:model="editing.notification_email" type="email" label="Notification email" required />
            <flux:input wire:model="editing.sender_email" type="email" label="Sender email (optional)" />
            <div class="flex justify-end gap-2">
                <flux:button type="button" x-on:click="$flux.modal('edit-team').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
