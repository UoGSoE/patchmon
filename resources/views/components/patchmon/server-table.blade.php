@props(['servers', 'selectable' => false])

@if ($servers->isEmpty())
    <div class="mt-6">
        {{ $empty ?? '' }}
    </div>
@else
    <flux:table :paginate="$servers" class="mt-4">
        <flux:table.columns>
            @if ($selectable)
                <flux:table.column />
            @endif
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
                    @if ($selectable)
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" value="{{ $server->id }}" />
                        </flux:table.cell>
                    @endif
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('servers.show', $server)" wire:navigate>{{ $server->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="{{ $server->os_type->colour() }}">{{ $server->os_type->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($server->team)
                            {{ $server->team->name }}
                        @else
                            <span class="text-zinc-400">Unassigned</span>
                        @endif
                    </flux:table.cell>
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
                        <div class="flex items-center gap-1">
                            @if ($server->isCurrentlySilenced())
                                <flux:tooltip content="Silenced until {{ $server->silenced_until->format('D j M') }}{{ $server->silence_reason ? ' — '.$server->silence_reason : '' }}">
                                    <flux:badge size="sm" color="amber" icon="speaker-x-mark" />
                                </flux:tooltip>
                            @elseif ($server->silenced_from && $server->silenced_from->isFuture())
                                <flux:tooltip content="Silence scheduled {{ $server->silenced_from->format('D j M') }} – {{ $server->silenced_until->format('D j M') }}{{ $server->silence_reason ? ' — '.$server->silence_reason : '' }}">
                                    <flux:badge size="sm" color="amber" icon="clock" />
                                </flux:tooltip>
                            @endif

                            @if ($server->isInactive())
                                <flux:badge size="sm" color="zinc" icon="archive-box">Inactive</flux:badge>
                            @elseif ($server->alerting_since)
                                <flux:badge size="sm" color="red" icon="exclamation-triangle">Due {{ $server->alerting_since->diffForHumans() }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="green">OK</flux:badge>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
@endif
