@props(['servers'])

@if ($servers->isEmpty())
    <div class="mt-6">
        {{ $empty ?? '' }}
    </div>
@else
    <flux:table class="mt-4">
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>OS</flux:table.column>
            <flux:table.column>Team</flux:table.column>
            <flux:table.column>Schedule</flux:table.column>
            <flux:table.column>Last patched</flux:table.column>
            <flux:table.column>Status</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($servers as $server)
                <flux:table.row :key="'server-row-'.$server->id">
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('servers.show', $server)" wire:navigate>{{ $server->name }}</flux:link>
                        @if ($server->location)
                            <flux:text size="sm" class="mt-0.5">{{ $server->location }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="{{ $server->os_type->colour() }}">{{ $server->os_type->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $server->team->name }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $server->intervalLabel() }}
                        <flux:text size="sm" class="mt-0.5">{{ $server->grace_value }} {{ strtolower($server->grace_units->label()) }} grace</flux:text>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($server->last_patched_at)
                            {{ $server->last_patched_at->diffForHumans() }}
                        @else
                            <span class="text-zinc-400">Never</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($server->alerting_since)
                            <flux:badge size="sm" color="red" icon="exclamation-triangle">Due {{ $server->alerting_since->diffForHumans() }}</flux:badge>
                        @elseif ($server->silenced_until && $server->silenced_until->isFuture())
                            <flux:badge size="sm" color="zinc" icon="speaker-x-mark">Silenced until {{ $server->silenced_until->format('D j M') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="green">OK</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
@endif
