<div class="max-w-2xl">
    <flux:heading size="xl">Edit {{ $job->name }}</flux:heading>

    <x-cronmon.job-form
        :form="$form"
        :teams="$teams"
        :interval-options="$intervalOptions"
        :grace-unit-options="$graceUnitOptions"
        submit-label="Save changes"
        :cancel-url="route('jobs.show', $job)"
    />
</div>
