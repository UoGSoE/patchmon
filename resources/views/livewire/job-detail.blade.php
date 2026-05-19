<div class="max-w-3xl">
    <flux:button :href="route('home')" variant="ghost" icon="arrow-left" size="sm" wire:navigate>Back to jobs</flux:button>

    <div class="mt-4 flex items-center gap-3">
        <flux:heading size="xl">{{ $job->name }}</flux:heading>
        @if ($job->alerting_since)
            <flux:badge color="red">Awol since {{ $job->alerting_since->diffForHumans() }}</flux:badge>
        @elseif ($job->isCurrentlySilenced())
            <flux:badge color="zinc">Silenced</flux:badge>
        @endif
    </div>

    @if ($job->description)
        <flux:text class="mt-2">{{ $job->description }}</flux:text>
    @endif

    <div class="mt-6 grid gap-6 sm:grid-cols-2">
        <div>
            <flux:heading size="sm">Schedule</flux:heading>
            <flux:text class="mt-1">
                @if ($job->cron_expression)
                    Cron: <code>{{ $job->cron_expression }}</code>
                @else
                    {{ $job->schedule_frequency }} × {{ strtolower($job->schedule_interval->label()) }}
                @endif
            </flux:text>
            <flux:text size="sm" class="mt-1">{{ $job->grace_value }} {{ strtolower($job->grace_units->label()) }} grace</flux:text>
        </div>

        <div>
            <flux:heading size="sm">Owner</flux:heading>
            <flux:text class="mt-1">
                @if ($job->team)
                    Team: {{ $job->team->name }}
                @else
                    Personal — {{ $job->user->name ?? $job->user->email }}
                @endif
            </flux:text>
            <flux:text size="sm" class="mt-1">Created by {{ $job->createdBy->name ?? $job->createdBy->email }}</flux:text>
        </div>
    </div>

    <div class="mt-6">
        <flux:heading size="sm">Check-in URL</flux:heading>
        <flux:text size="sm">Curl this URL from your job. Treat it like a webhook URL.</flux:text>
        <flux:input class="mt-2 font-mono" readonly :value="$checkInUrl" copyable />
    </div>

    <div class="mt-8">
        <flux:heading size="sm">Recent check-ins</flux:heading>
        @if ($recentCheckIns->isEmpty())
            <flux:text class="mt-2">No check-ins yet.</flux:text>
        @else
            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>When</flux:table.column>
                    <flux:table.column>Source IP</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($recentCheckIns as $checkIn)
                        <flux:table.row>
                            <flux:table.cell>{{ $checkIn->checked_in_at->toDayDateTimeString() }} ({{ $checkIn->checked_in_at->diffForHumans() }})</flux:table.cell>
                            <flux:table.cell>{{ $checkIn->source_ip ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <div class="mt-8 flex gap-3">
        <flux:button :href="route('jobs.edit', $job)" wire:navigate>Edit</flux:button>
        <flux:modal.trigger name="delete-job">
            <flux:button variant="danger">Delete</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="delete-job" class="md:w-96">
        <div class="space-y-4">
            <flux:heading size="lg">Delete this job?</flux:heading>
            <flux:text>
                This removes <strong>{{ $job->name }}</strong> and its check-in history.
                The check-in URL will stop working.
            </flux:text>
            <div class="flex justify-end gap-3">
                <flux:button x-on:click="$flux.modal('delete-job').close()" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="delete" variant="danger">Yes, delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
