<div class="max-w-2xl">
    <flux:heading size="xl">New job</flux:heading>
    <flux:text class="mt-2">Tell Cronmon when this job should be checking in.</flux:text>

    <x-cronmon.job-form
        :form="$form"
        :teams="$teams"
        :interval-options="$intervalOptions"
        :grace-unit-options="$graceUnitOptions"
        submit-label="Create job"
        :cancel-url="route('home')"
    />
</div>
