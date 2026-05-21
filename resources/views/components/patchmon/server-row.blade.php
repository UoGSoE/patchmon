@props(['server'])

<a href="{{ route('servers.show', $server) }}" wire:navigate wire:key="server-row-{{ $server->id }}" class="block mt-3">
<flux:card class="flex items-start justify-between gap-4 hover:shadow-md transition-shadow">
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
            @if ($server->alerting_since)
                <flux:icon.exclamation-triangle variant="micro" class="text-red-500" />
            @endif
            @if ($server->silenced_until && $server->silenced_until->isFuture())
                <flux:icon.speaker-x-mark variant="micro" class="text-zinc-400" />
            @endif
            <flux:heading size="lg">{{ $server->name }}</flux:heading>
            <flux:badge size="sm" color="{{ $server->os_type->colour() }}">{{ $server->os_type->label() }}</flux:badge>
        </div>
        @if ($server->description)
            <flux:text class="mt-1">{{ $server->description }}</flux:text>
        @endif
        <flux:text size="sm" class="mt-2">
            Every {{ $server->interval_months }} {{ \Illuminate\Support\Str::plural('month', $server->interval_months) }}
            · {{ $server->grace_value }} {{ strtolower($server->grace_units->label()) }} grace
            · Team: {{ $server->team->name }}
            @if ($server->location)
                · Location: {{ $server->location }}
            @endif
        </flux:text>
        <flux:text size="sm" class="mt-1">
            @if ($server->last_patched_at)
                Last patched {{ $server->last_patched_at->diffForHumans() }}
            @else
                Not patched yet
            @endif
            @if ($server->alerting_since)
                · Awol since {{ $server->alerting_since->diffForHumans() }}
            @endif
            @if ($server->silenced_until && $server->silenced_until->isFuture())
                · Silenced until {{ $server->silenced_until->format('D j M, H:i') }}
            @endif
        </flux:text>
    </div>
</flux:card>
</a>
