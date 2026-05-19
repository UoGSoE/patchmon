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
        </flux:tabs>

        <flux:tab.panel name="mine">
            @forelse ($this->myJobs as $job)
                <x-cronmon.job-row :job="$job" />
            @empty
                <flux:text class="mt-6">No personal jobs yet.</flux:text>
            @endforelse
        </flux:tab.panel>

        <flux:tab.panel name="teams">
            @forelse ($this->teamJobs as $job)
                <x-cronmon.job-row :job="$job" />
            @empty
                @if ($this->userIsInAnyTeam)
                    <flux:text class="mt-6">None of your teams have jobs yet.</flux:text>
                @else
                    <flux:text class="mt-6">You are not a member of any teams.</flux:text>
                @endif
            @endforelse
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
