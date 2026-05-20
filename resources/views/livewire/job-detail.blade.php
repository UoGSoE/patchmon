<div>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                @if ($job->alerting_since)
                    <flux:icon.exclamation-triangle variant="micro" class="text-red-500" />
                @endif
                <flux:heading size="xl">{{ $job->name }}</flux:heading>
            </div>
            @if ($job->description)
                <flux:text class="mt-2">{{ $job->description }}</flux:text>
            @endif
            @if ($job->alerting_since)
                <flux:text size="sm" class="mt-1">
                    @if ($job->alerting_since)
                        Awol since {{ $job->alerting_since->diffForHumans() }}@if ($job->isCurrentlySilenced()) · @endif
                    @endif
                </flux:text>
            @endif
        </div>

        <div class="flex gap-2">
            <flux:button wire:click="openEdit" icon="pencil-square" tooltip="Edit" />

            <flux:modal.trigger name="delete-job">
                <flux:button icon="trash" tooltip="Delete" variant="danger" />
            </flux:modal.trigger>
        </div>
    </div>

    <div class="mt-6 grid gap-6 sm:grid-cols-2 max-w-1/2">
        <flux:card>
            <flux:heading size="sm">Schedule</flux:heading>
            <flux:text class="mt-1">
                @if ($job->cron_expression)
                    Cron: <code>{{ $job->cron_expression }}</code>
                @else
                    {{ $job->schedule_frequency }} × {{ strtolower($job->schedule_interval->label()) }}
                @endif
            </flux:text>
            <flux:text size="sm" class="mt-1">{{ $job->grace_value }} {{ strtolower($job->grace_units->label()) }} grace</flux:text>
        </flux:card>

        <flux:card>
            <flux:heading size="sm">Owner</flux:heading>
            <flux:text class="mt-1">
                @if ($job->team)
                    Team: {{ $job->team->name }}
                @else
                    Personal — {{ $job->user->full_name ?: $job->user->email }}
                @endif
            </flux:text>
            <flux:text size="sm" class="mt-1">Created by {{ $job->createdBy->full_name ?: $job->createdBy->email }}</flux:text>
        </flux:card>
    </div>

    <div class="mt-6 max-w-1/2">
        <flux:heading size="sm">Check-in URL</flux:heading>
        <flux:text size="sm">Curl this URL from your job. Treat it like a webhook URL.</flux:text>
        <flux:input class="mt-2 font-mono" readonly :value="$checkInUrl" copyable />
    </div>

    <flux:fieldset class="mt-8">
        <div class="max-w-1/2 space-y-6">
        <flux:switch wire:model.live="silenced" label="Silenced" description="When silenced, Cronmon won't alert about this job."/>
        @if ($silenced)
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model.blur="silenceUntil" type="datetime-local" label="Silenced until" />
                <flux:input wire:model.blur="silenceReason" label="Reason (optional)" placeholder="Building works" />
            </div>
        @endif
        </div>
    </flux:fieldset>

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
                        <flux:table.row wire:key="check-in-row-{{ $checkIn->id }}">
                            <flux:table.cell>{{ $checkIn->checked_in_at->toDayDateTimeString() }} ({{ $checkIn->checked_in_at->diffForHumans() }})</flux:table.cell>
                            <flux:table.cell>{{ $checkIn->source_ip ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <flux:modal name="job-form" variant="flyout" class="max-w-2xl">
        <div class="space-y-6">
            <flux:heading size="lg">Edit job</flux:heading>
            <x-cronmon.job-form
                :form="$form"
                :teams="$teams"
                :interval-options="$intervalOptions"
                :grace-unit-options="$graceUnitOptions"
                submit-label="Save changes"
                cancel-action="$flux.modal('job-form').close()"
            />
        </div>
    </flux:modal>

    <flux:modal name="delete-job" variant="flyout" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">Delete this job?</flux:heading>
            <flux:text>
                This removes <strong>{{ $job->name }}</strong> and its check-in history.
                The check-in URL will stop working.
            </flux:text>
            <div class="flex justify-end gap-2">
                <flux:button x-on:click="$flux.modal('delete-job').close()">Cancel</flux:button>
                <flux:button wire:click="delete" variant="danger">Yes, delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
