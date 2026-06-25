<div>
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Activity log</flux:heading>
            <flux:text class="mt-2">Who did what, when, and from where, across the whole estate.</flux:text>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap items-end gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Search name or server"
            class="max-w-xs"
        />

        <flux:select wire:model.live="userId" variant="combobox" :filter="false" placeholder="Any user" clearable>
            <x-slot name="input">
                <flux:select.input wire:model.live="userSearch" />
            </x-slot>

            @foreach ($this->users as $user)
                <flux:select.option value="{{ $user->id }}" wire:key="user-{{ $user->id }}">
                    {{ $user->full_name }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="serverId" variant="combobox" :filter="false" placeholder="Any server" clearable>
            <x-slot name="input">
                <flux:select.input wire:model.live="serverSearch" />
            </x-slot>

            @foreach ($this->servers as $server)
                <flux:select.option value="{{ $server->id }}" wire:key="server-{{ $server->id }}">
                    {{ $server->name }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:date-picker wire:model.live="dateRange" mode="range" with-presets placeholder="Any date" />
    </div>

    <flux:table class="mt-6">
        <flux:table.columns>
            <flux:table.column>When</flux:table.column>
            <flux:table.column>Who</flux:table.column>
            <flux:table.column>Server</flux:table.column>
            <flux:table.column>Description</flux:table.column>
            <flux:table.column>Source IP</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->entries as $entry)
                <flux:table.row wire:key="activity-row-{{ $entry->id }}">
                    <flux:table.cell>{{ $entry->created_at->toDayDateTimeString() }} ({{ $entry->created_at->diffForHumans() }})</flux:table.cell>
                    <flux:table.cell>
                        @if ($entry->user_name)
                            {{ $entry->user_name }}
                        @else
                            <span class="text-zinc-400">{{ $entry->actorLabel() }}</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $entry->server_name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $entry->description }}</flux:table.cell>
                    <flux:table.cell>{{ $entry->source_ip ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text>No activity matches your filters.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->entries->links() }}
    </div>
</div>
