@props([
    'form',
    'teams',
    'osTypeOptions',
    'graceUnitOptions',
    'existingLocations' => [],
    'submitLabel' => 'Save',
    'cancelAction' => null,
])

<form wire:submit="save" class="space-y-6">
    <flux:input wire:model="form.name" label="Name" required />

    <flux:textarea wire:model="form.description" label="Description" rows="2" />

    <flux:select
        wire:model="form.location"
        variant="combobox"
        label="Location (optional)"
        description="Where does this server live? Pick from existing locations or type a new one."
        placeholder="No location set"
        clearable
    >
        @foreach ($existingLocations as $location)
            <flux:select.option value="{{ $location }}">{{ $location }}</flux:select.option>
        @endforeach
        <flux:select.option.create>Use as new location</flux:select.option.create>
    </flux:select>

    <flux:select wire:model="form.os_type" label="OS type">
        @foreach ($osTypeOptions as $os)
            <flux:select.option value="{{ $os->value }}">{{ $os->label() }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:select wire:model="form.interval_months" label="Patch every">
        <flux:select.option value="1">Monthly</flux:select.option>
        <flux:select.option value="3">Quarterly</flux:select.option>
        <flux:select.option value="6">Twice-yearly</flux:select.option>
        <flux:select.option value="12">Yearly</flux:select.option>
    </flux:select>

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

    <flux:select wire:model="form.team_id" label="Team">
        <flux:select.option value="">Choose a team…</flux:select.option>
        @foreach ($teams as $team)
            <flux:select.option value="{{ $team->id }}">{{ $team->name }}</flux:select.option>
        @endforeach
    </flux:select>

    <div>
        <flux:heading size="sm">Email overrides</flux:heading>
        <flux:text size="sm">Leave blank to use the team's defaults.</flux:text>
        <div class="mt-2 space-y-3">
            <flux:input wire:model="form.notification_email" type="email" label="Alerts go to" />
            <flux:input wire:model="form.sender_email" type="email" label="Alerts come from" />
        </div>
    </div>

    <div class="flex items-center justify-end gap-2">
        @if ($cancelAction)
            <flux:button type="button" x-on:click="{{ $cancelAction }}">Cancel</flux:button>
        @endif
        <flux:button type="submit" variant="primary">{{ $submitLabel }}</flux:button>
    </div>
</form>
