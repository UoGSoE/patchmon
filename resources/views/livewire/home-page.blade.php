<div>
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Servers</flux:heading>
            <flux:text class="mt-2">Overdue servers come to the top so you can see them at a glance.</flux:text>
        </div>
        <flux:button wire:click="openCreate" icon="plus">New server</flux:button>
    </div>

    <flux:tab.group class="mt-6">
        <flux:tabs wire:model.live="tab">
            <flux:tab name="teams">Team servers</flux:tab>
            <flux:tab name="alerting">Alerting servers</flux:tab>
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
                @foreach ($teams as $team)
                    <flux:select.option value="{{ $team->id }}">{{ $team->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="silencedFilter">
                <flux:select.option value="">Silenced &amp; active</flux:select.option>
                <flux:select.option value="active">Active only</flux:select.option>
                <flux:select.option value="silenced">Silenced only</flux:select.option>
            </flux:select>
            <flux:checkbox wire:model.live="excludeFilter" label="Exclude matches" />
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

        <flux:tab.panel name="alerting">
            <x-patchmon.server-table :servers="$this->alertingServers">
                <x-slot:empty>
                    <flux:text>Nothing is currently alerting.</flux:text>
                </x-slot:empty>
            </x-patchmon.server-table>
        </flux:tab.panel>
    </flux:tab.group>

    <flux:modal name="server-form" variant="flyout" class="max-w-2xl">
        <div class="space-y-6">
            <flux:heading size="lg">New server</flux:heading>
            <x-patchmon.server-form
                :form="$form"
                :teams="$teams"
                :os-type-options="$osTypeOptions"
                :grace-unit-options="$graceUnitOptions"
                :existing-locations="$existingLocations"
                submit-label="Create server"
                cancel-action="$flux.modal('server-form').close()"
            />
        </div>
    </flux:modal>
</div>
