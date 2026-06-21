<div>
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Servers</flux:heading>
            <flux:text class="mt-2">Overdue servers come to the top so you can see them at a glance.</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="export" icon="table-cells">Export</flux:button>
            <flux:dropdown>
                <flux:button icon="arrow-down-tray" icon:trailing="chevron-down">Patch script</flux:button>
                <flux:navmenu>
                    <flux:navmenu.item :href="route('scripts.record-patch')">Linux</flux:navmenu.item>
                    <flux:navmenu.item :href="route('scripts.record-patch-ps')">Windows</flux:navmenu.item>
                </flux:navmenu>
            </flux:dropdown>
            <flux:button wire:click="openCreate" icon="plus">New server</flux:button>
        </div>
    </div>

    <flux:tab.group class="mt-6">
        <flux:tabs wire:model.live="tab">
            <flux:tab name="teams">Team servers</flux:tab>
            <flux:tab name="all">All servers</flux:tab>
            <flux:tab name="alerting">Alerting servers</flux:tab>
            <flux:tab name="silenced">Silenced servers</flux:tab>
            @if (auth()->user()->is_staff)
                <flux:tab name="unassigned">Unassigned servers</flux:tab>
            @endif
        </flux:tabs>

        <div class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto_auto_auto] md:items-end">
            <flux:input
                wire:model.live.debounce.300ms="filter"
                placeholder="Filter by name, description, or location"
                icon="magnifying-glass"
                clearable
            />
            <flux:select wire:model.live="osFilter" placeholder="Any OS">
                <flux:select.option value="">Any OS</flux:select.option>
                @foreach ($osTypeOptions as $os)
                    <flux:select.option value="{{ $os->value }}">{{ $os->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="teamFilter" placeholder="Any team">
                <flux:select.option value="">Any team</flux:select.option>
                @foreach ($allTeams as $team)
                    <flux:select.option value="{{ $team->id }}">{{ $team->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="silencedFilter">
                <flux:select.option value="">Silenced &amp; active</flux:select.option>
                <flux:select.option value="active">Active only</flux:select.option>
                <flux:select.option value="silenced">Silenced only</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="perPage">
                <flux:select.option value="25">25 per page</flux:select.option>
                <flux:select.option value="50">50 per page</flux:select.option>
                <flux:select.option value="100">100 per page</flux:select.option>
                <flux:select.option value="all">Show all</flux:select.option>
            </flux:select>
        </div>

        <flux:tab.panel name="teams">
            <x-patchmon.server-table :servers="$this->teamServers">
                <x-slot:empty>
                    @if ($this->userIsInAnyTeam)
                        <flux:text>No servers match your filters.</flux:text>
                    @else
                        <flux:text>You are not a member of any teams.</flux:text>
                    @endif
                </x-slot:empty>
            </x-patchmon.server-table>
        </flux:tab.panel>

        <flux:tab.panel name="all">
            <x-patchmon.server-table :servers="$this->allServers">
                <x-slot:empty>
                    <flux:text>No servers match your filters.</flux:text>
                </x-slot:empty>
            </x-patchmon.server-table>
        </flux:tab.panel>

        <flux:tab.panel name="alerting">
            <x-patchmon.server-table :servers="$this->alertingServers">
                <x-slot:empty>
                    <flux:text>Nothing is currently alerting.</flux:text>
                </x-slot:empty>
            </x-patchmon.server-table>
        </flux:tab.panel>

        <flux:tab.panel name="silenced">
            <x-patchmon.server-table :servers="$this->silencedServers">
                <x-slot:empty>
                    <flux:text>No servers are currently silenced.</flux:text>
                </x-slot:empty>
            </x-patchmon.server-table>
        </flux:tab.panel>

        @if (auth()->user()->is_staff)
            <flux:tab.panel name="unassigned">
                @if (! $this->unassignedServers->isEmpty())
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <flux:checkbox
                            wire:model.live="selectAllMatching"
                            label="Select all {{ $this->unassignedServers->total() }} matching this filter"
                        />
                        <flux:button
                            wire:click="openAllocate"
                            variant="primary"
                            icon="user-group"
                            :disabled="$this->selectedCount === 0"
                        >
                            Allocate {{ $this->selectedCount }} {{ str('server')->plural($this->selectedCount) }}…
                        </flux:button>
                    </div>
                @endif

                <x-patchmon.server-table
                    :servers="$this->unassignedServers"
                    :selectable="true"
                >
                    <x-slot:empty>
                        <flux:text>No servers are awaiting allocation.</flux:text>
                    </x-slot:empty>
                </x-patchmon.server-table>
            </flux:tab.panel>
        @endif
    </flux:tab.group>

    <flux:modal name="bulk-allocate" variant="flyout" class="max-w-lg">
        <form wire:submit="bulkAllocate" class="space-y-6">
            <flux:heading size="lg">Allocate {{ $this->selectedCount }} {{ str('server')->plural($this->selectedCount) }}</flux:heading>

            <flux:select wire:model="allocateTeamId" label="Team">
                <flux:select.option value="">Choose a team…</flux:select.option>
                @foreach ($allTeams as $team)
                    <flux:select.option value="{{ $team->id }}">{{ $team->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="allocateIntervalMonths" label="Patch every">
                <flux:select.option value="1">Monthly</flux:select.option>
                <flux:select.option value="3">Quarterly</flux:select.option>
                <flux:select.option value="6">Twice-yearly</flux:select.option>
                <flux:select.option value="12">Yearly</flux:select.option>
            </flux:select>

            <div>
                <flux:heading size="sm">Grace period</flux:heading>
                <flux:text size="sm">How late can it be before we alert?</flux:text>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="allocateGraceValue" type="number" min="1" />
                    <flux:select wire:model="allocateGraceUnits">
                        @foreach ($graceUnitOptions as $unit)
                            <flux:select.option value="{{ $unit->value }}">{{ $unit->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Allocate {{ $this->selectedCount }} {{ str('server')->plural($this->selectedCount) }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="server-form" variant="flyout" class="max-w-2xl">
        <div class="space-y-6">
            <flux:heading size="lg">New server</flux:heading>
            <x-patchmon.server-form
                :form="$form"
                :teams="$userTeams"
                :os-type-options="$osTypeOptions"
                :grace-unit-options="$graceUnitOptions"
                :existing-locations="$existingLocations"
                submit-label="Create server"
                cancel-action="$flux.modal('server-form').close()"
            />
        </div>
    </flux:modal>
</div>
