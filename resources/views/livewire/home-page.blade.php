<div>
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Your jobs</flux:heading>
            <flux:text class="mt-2">Jobs that are awol come to the top so you can see them at a glance.</flux:text>
        </div>
        <flux:button wire:click="openCreate" icon="plus">New job</flux:button>
    </div>

    <flux:tab.group class="mt-6">
        <flux:tabs wire:model.live="tab">
            <flux:tab name="mine">My jobs</flux:tab>
            <flux:tab name="teams">Team jobs</flux:tab>
            <flux:tab name="alerting">Alerting jobs</flux:tab>
        </flux:tabs>

        <div class="mt-4 flex flex-col gap-3 md:flex-row md:items-center">
            <flux:input
                wire:model.live.debounce.300ms="filter"
                placeholder="Filter by name or description"
                icon="magnifying-glass"
                clearable
                class="md:max-w-md"
            />
            <flux:checkbox wire:model.live="excludeFilter" label="Exclude matches" />
        </div>

        <flux:tab.panel name="mine">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse ($this->myJobs as $job)
                    <x-cronmon.job-row :job="$job" />
                @empty
                    <flux:text class="mt-6">No personal jobs yet.</flux:text>
                @endforelse
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="teams">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse ($this->teamJobs as $job)
                    <x-cronmon.job-row :job="$job" />
                @empty
                    @if ($this->userIsInAnyTeam)
                        <flux:text class="mt-6">None of your teams have jobs yet.</flux:text>
                    @else
                        <flux:text class="mt-6">You are not a member of any teams.</flux:text>
                    @endif
                @endforelse
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="alerting">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse ($this->alertingJobs as $job)
                    <x-cronmon.job-row :job="$job" />
                @empty
                    <flux:text class="mt-6">Nothing is currently alerting.</flux:text>
                @endforelse
            </div>
        </flux:tab.panel>
    </flux:tab.group>

    <flux:modal name="job-form" variant="flyout" class="max-w-2xl">
        <div class="space-y-6">
            <flux:heading size="lg">New job</flux:heading>
            <x-cronmon.job-form
                :form="$form"
                :teams="$teams"
                :interval-options="$intervalOptions"
                :grace-unit-options="$graceUnitOptions"
                submit-label="Create job"
                cancel-action="$flux.modal('job-form').close()"
            />
        </div>
    </flux:modal>
</div>
