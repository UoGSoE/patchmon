@props(['job'])

<a href="{{ route('jobs.show', $job) }}" wire:navigate wire:key="job-row-{{ $job->id }}" class="block mt-3">
<flux:card class="flex items-start justify-between gap-4 hover:shadow-md transition-shadow">
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
            <flux:heading size="lg">{{ $job->name }}</flux:heading>
            @if ($job->alerting_since)
                <flux:badge color="red" size="sm">Awol since {{ $job->alerting_since->diffForHumans() }}</flux:badge>
            @elseif ($job->silenced_until && $job->silenced_until->isFuture())
                <flux:badge color="zinc" size="sm">Silenced until {{ $job->silenced_until->format('D j M, H:i') }}</flux:badge>
            @endif
        </div>
        @if ($job->description)
            <flux:text class="mt-1">{{ $job->description }}</flux:text>
        @endif
        <flux:text size="sm" class="mt-2">
            @if ($job->cron_expression)
                Cron: <code>{{ $job->cron_expression }}</code>
            @else
                {{ $job->schedule_frequency }} × {{ $job->schedule_interval?->label() }}
            @endif
            · {{ $job->grace_value }} {{ strtolower($job->grace_units->label()) }} grace
            @if ($job->team)
                · Team: {{ $job->team->name }}
            @endif
        </flux:text>
        <flux:text size="sm" class="mt-1">
            @if ($job->last_checked_in_at)
                Last check-in {{ $job->last_checked_in_at->diffForHumans() }}
            @else
                No check-ins yet
            @endif
        </flux:text>
    </div>
</flux:card>
</a>
