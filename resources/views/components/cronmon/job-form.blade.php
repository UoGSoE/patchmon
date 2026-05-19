@props([
    'form',
    'teams',
    'intervalOptions',
    'graceUnitOptions',
    'submitLabel' => 'Save',
    'cancelUrl',
])

<form wire:submit="save" class="mt-6 space-y-6">
    <flux:input wire:model="form.name" label="Name" required />

    <flux:textarea wire:model="form.description" label="Description" rows="2" />

    <div>
        <flux:heading size="sm">Schedule</flux:heading>
        <flux:radio.group wire:model.live="form.schedule_type" variant="segmented" class="mt-2">
            <flux:radio value="interval" label="Interval" />
            <flux:radio value="cron" label="Cron expression" />
        </flux:radio.group>

        @if ($form->schedule_type === 'interval')
            <div class="mt-3 flex gap-3">
                <flux:input wire:model="form.schedule_frequency" type="number" min="1" label="How many" class="w-32" />
                <flux:select wire:model="form.schedule_interval" label="per">
                    <flux:select.option value="">Choose…</flux:select.option>
                    @foreach ($intervalOptions as $interval)
                        <flux:select.option value="{{ $interval->value }}">{{ $interval->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @else
            <flux:input
                wire:model="form.cron_expression"
                label="Cron expression"
                placeholder="0 2 * * *"
                class="mt-3 font-mono"
            />
        @endif
    </div>

    <div>
        <flux:heading size="sm">Grace period</flux:heading>
        <flux:text size="sm">How late can it be before we alert?</flux:text>
        <div class="mt-2 flex gap-3">
            <flux:input wire:model="form.grace_value" type="number" min="1" class="w-32" />
            <flux:select wire:model="form.grace_units">
                @foreach ($graceUnitOptions as $unit)
                    <flux:select.option value="{{ $unit->value }}">{{ $unit->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div>
        <flux:heading size="sm">Owner</flux:heading>
        <flux:radio.group wire:model.live="form.ownership_type" variant="segmented" class="mt-2">
            <flux:radio value="mine" label="Personal (just me)" />
            <flux:radio value="team" label="A team" :disabled="$teams->isEmpty()" />
        </flux:radio.group>

        @if ($form->ownership_type === 'team')
            <flux:select wire:model="form.team_id" label="Team" class="mt-3">
                <flux:select.option value="">Choose a team…</flux:select.option>
                @foreach ($teams as $team)
                    <flux:select.option value="{{ $team->id }}">{{ $team->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    <div>
        <flux:heading size="sm">Email overrides</flux:heading>
        <flux:text size="sm">Leave blank to use the owner's defaults.</flux:text>
        <div class="mt-2 space-y-3">
            <flux:input wire:model="form.notification_email" type="email" label="Alerts go to" placeholder="alerts@example.ac.uk" />
            <flux:input wire:model="form.sender_email" type="email" label="Alerts come from" placeholder="cronmon-noreply@example.ac.uk" />
        </div>
    </div>

    <div class="flex items-center gap-3">
        <flux:button type="submit" variant="primary">{{ $submitLabel }}</flux:button>
        <flux:button :href="$cancelUrl" variant="ghost" wire:navigate>Cancel</flux:button>
    </div>
</form>
