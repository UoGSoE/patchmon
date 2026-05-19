<div class="max-w-2xl">
    <flux:heading size="xl">My settings</flux:heading>
    <flux:text class="mt-2">Defaults that apply to your personal jobs.</flux:text>

    <div class="mt-8">
        <flux:heading size="sm">Email preferences</flux:heading>
        <flux:text size="sm">Leave blank to use your account email ({{ $user->email }}).</flux:text>

        <form wire:submit="saveEmails" class="mt-3 space-y-3">
            <flux:input wire:model="notificationEmail" type="email" label="Alerts come to" placeholder="alerts@example.ac.uk" />
            <flux:input wire:model="senderEmail" type="email" label="Alerts come from" placeholder="cronmon-noreply@example.ac.uk" />
            <flux:button type="submit" variant="primary">Save preferences</flux:button>
        </form>
    </div>

    <div class="mt-10">
        <flux:heading size="sm">Silence everything</flux:heading>
        <flux:text size="sm">
            Stop Cronmon emailing you about any of your personal jobs.
            Team jobs aren't affected — silence those from the team page.
        </flux:text>

        <div class="mt-3 space-y-3">
            <flux:switch wire:model.live="silenced" label="Silenced" />
            @if ($silenced)
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model.blur="silenceUntil" type="datetime-local" label="Silenced until" />
                    <flux:input wire:model.blur="silenceReason" label="Reason (optional)" placeholder="On leave" />
                </div>
            @endif
        </div>
    </div>
</div>
