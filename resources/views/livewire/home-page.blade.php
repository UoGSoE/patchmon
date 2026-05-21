<div>
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Servers</flux:heading>
            <flux:text class="mt-2">Servers that are awol come to the top so you can see them at a glance.</flux:text>
        </div>
        <flux:button wire:click="openCreate" icon="plus">New server</flux:button>
    </div>

    <flux:tab.group class="mt-6">
        <flux:tabs wire:model.live="tab">
            <flux:tab name="teams">Team servers</flux:tab>
            <flux:tab name="alerting">Alerting servers</flux:tab>
        </flux:tabs>

        <div class="mt-4 flex flex-col gap-3 md:flex-row md:items-center">
            <flux:input
                wire:model.live.debounce.300ms="filter"
                placeholder="Filter by name, description, or location"
                icon="magnifying-glass"
                clearable
                class="md:max-w-md"
            />
            <flux:checkbox wire:model.live="excludeFilter" label="Exclude matches" />
        </div>

        <flux:tab.panel name="teams">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse ($this->teamServers as $server)
                    <x-patchmon.server-row :server="$server" />
                @empty
                    @if ($this->userIsInAnyTeam)
                        <flux:text class="mt-6">None of your teams have servers yet.</flux:text>
                    @else
                        <flux:text class="mt-6">You are not a member of any teams.</flux:text>
                    @endif
                @endforelse
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="alerting">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse ($this->alertingServers as $server)
                    <x-patchmon.server-row :server="$server" />
                @empty
                    <flux:text class="mt-6">Nothing is currently alerting.</flux:text>
                @endforelse
            </div>
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
