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

        <div class="mt-3">
            @if ($user->isCurrentlySilenced())
                <flux:text>
                    Silenced until <strong>{{ $user->silenced_until->format('D j M, H:i') }}</strong>
                    @if ($user->silence_reason)
                        — {{ $user->silence_reason }}
                    @endif
                </flux:text>
                <flux:button wire:click="unsilence" class="mt-2" icon="speaker-wave">Unsilence me</flux:button>
            @else
                <flux:modal.trigger name="silence-self">
                    <flux:button icon="speaker-x-mark">Silence me…</flux:button>
                </flux:modal.trigger>
            @endif
        </div>
    </div>

    <flux:modal name="silence-self" variant="flyout">
        <form wire:submit="silence" class="space-y-6">
            <flux:heading size="lg">Silence yourself</flux:heading>
            <flux:text>Cronmon won't email you about any of your personal jobs until the time you pick.</flux:text>

            <flux:input wire:model="silenceUntil" type="datetime-local" label="Silenced until" />
            <flux:input wire:model="silenceReason" label="Reason (optional)" placeholder="On leave" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" x-on:click="$flux.modal('silence-self').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Silence</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
