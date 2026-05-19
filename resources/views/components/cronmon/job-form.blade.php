@props([
    'form',
    'teams',
    'intervalOptions',
    'graceUnitOptions',
    'submitLabel' => 'Save',
    'cancelAction' => null,
])

<form wire:submit="save" class="space-y-6">
    <flux:input wire:model="form.name" label="Name" required />

    <flux:textarea wire:model="form.description" label="Description" rows="2" />

    <div>
        <flux:heading size="sm">Schedule</flux:heading>
        <flux:text size="sm">Use either an interval or a cron expression — whichever fits the job.</flux:text>
        <div class="mt-2 grid gap-3 sm:grid-cols-3">
            <flux:input wire:model="form.schedule_frequency" type="number" min="1" label="How many" />
            <flux:select wire:model="form.schedule_interval" label="Per" class="sm:col-span-2">
                <flux:select.option value="">—</flux:select.option>
                @foreach ($intervalOptions as $interval)
                    <flux:select.option value="{{ $interval->value }}">{{ $interval->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <flux:input
            wire:model="form.cron_expression"
            label="Or cron expression"
            placeholder="0 2 * * *"
            class="mt-3 font-mono"
        />
    </div>

    <div>
        <flux:heading size="sm">Grace period</flux:heading>
        <flux:text size="sm">How late can it be before we alert?</flux:text>
        <div class="mt-2 grid gap-3 sm:grid-cols-2">
            <flux:input wire:model="form.grace_value" type="number" min="1" />
            <flux:select wire:model="form.grace_units">
                @foreach ($graceUnitOptions as $unit)
                    <flux:select.option value="{{ $unit->value }}">{{ $unit->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <flux:select wire:model="form.team_id" label="Team (optional)" description="Leave blank to make this a personal job.">
        <flux:select.option value="">Personal — just me</flux:select.option>
        @foreach ($teams as $team)
            <flux:select.option value="{{ $team->id }}">{{ $team->name }}</flux:select.option>
        @endforeach
    </flux:select>

    <div>
        <flux:heading size="sm">Email overrides</flux:heading>
        <flux:text size="sm">Leave blank to use the owner's defaults.</flux:text>
        <div class="mt-2 space-y-3">
            <flux:input wire:model="form.notification_email" type="email" label="Alerts go to" placeholder="alerts@example.ac.uk" />
            <flux:input wire:model="form.sender_email" type="email" label="Alerts come from" placeholder="cronmon-noreply@example.ac.uk" />
        </div>
    </div>

    <div class="flex items-center justify-end gap-2">
        @if ($cancelAction)
            <flux:button type="button" x-on:click="{{ $cancelAction }}">Cancel</flux:button>
        @endif
        <flux:button type="submit" variant="primary">{{ $submitLabel }}</flux:button>
    </div>
</form>
