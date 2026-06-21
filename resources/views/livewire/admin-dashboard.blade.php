<div class="max-w-5xl">
    @if (auth()->user()->is_admin)
    <flux:heading size="lg">Manage</flux:heading>

    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <a href="{{ route('admin.teams.index') }}" wire:navigate class="h-full">
            <flux:card class="h-full hover:bg-zinc-50 dark:hover:bg-zinc-700">
                <flux:heading size="sm">Teams</flux:heading>
                <flux:text size="sm" class="mt-1">Create teams, manage membership, silence as a group.</flux:text>
            </flux:card>
        </a>

        <a href="{{ route('admin.users.index') }}" wire:navigate class="h-full">
            <flux:card class="h-full hover:bg-zinc-50 dark:hover:bg-zinc-700">
                <flux:heading size="sm">Users</flux:heading>
                <flux:text size="sm" class="mt-1">Edit, promote and remove user accounts.</flux:text>
            </flux:card>
        </a>

        <a href="{{ route('admin.api-tokens.index') }}" wire:navigate class="h-full">
            <flux:card class="h-full hover:bg-zinc-50 dark:hover:bg-zinc-700">
                <flux:heading size="sm">API tokens</flux:heading>
                <flux:text size="sm" class="mt-1">Audit and revoke API tokens across every user.</flux:text>
            </flux:card>
        </a>
    </div>

    <flux:separator class="my-8" />
    @endif

    <flux:heading size="xl">Patching overview</flux:heading>
    <flux:text class="mt-2">A quick read on where the estate is.</flux:text>

    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <div class="rounded-lg bg-zinc-100 p-4 dark:bg-zinc-800">
            <div class="flex h-8 items-center text-sm font-medium text-zinc-600 dark:text-zinc-400">Total servers</div>
            <div class="text-4xl font-bold text-zinc-900 dark:text-zinc-100">{{ $totalCount }}</div>
        </div>

        <div class="rounded-lg bg-red-100 p-4 dark:bg-red-900/40">
            <div class="flex h-8 items-center text-sm font-medium text-red-800 dark:text-red-300">Overdue</div>
            <div class="text-4xl font-bold text-red-900 dark:text-red-100">{{ $overdueCount }}</div>
            @if ($overdueCount > 0)
                <div class="mt-2 flex flex-wrap justify-between gap-x-2 text-xs text-red-800 dark:text-red-300">
                    <span><span class="font-semibold">1–7d:</span> {{ $overdueSeverityBands['mild'] }}</span>
                    <span><span class="font-semibold">8–30d:</span> {{ $overdueSeverityBands['moderate'] }}</span>
                    <span><span class="font-semibold">30+d:</span> {{ $overdueSeverityBands['severe'] }}</span>
                </div>
            @endif
        </div>

        <div class="rounded-lg bg-amber-100 p-4 dark:bg-amber-900/40">
            <div class="flex h-8 items-center text-sm font-medium text-amber-800 dark:text-amber-300">Silenced</div>
            <div class="text-4xl font-bold text-amber-900 dark:text-amber-100">{{ $silencedCount }}</div>
        </div>

        <div class="rounded-lg bg-green-100 p-4 dark:bg-green-900/40">
            <div class="flex h-8 items-center text-sm font-medium text-green-800 dark:text-green-300">Patched in 30 days</div>
            <div class="text-4xl font-bold text-green-900 dark:text-green-100">{{ $patchedRecentlyCount }}</div>
        </div>

        <div class="rounded-lg bg-zinc-100 p-4 dark:bg-zinc-800">
            <div class="flex h-8 items-center gap-1 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                Never checked in
                <flux:tooltip toggleable>
                    <flux:button icon="information-circle" size="sm" variant="ghost" />
                    <flux:tooltip.content class="max-w-[20rem] space-y-2">
                        <p>Servers that exist in Patchmon but have never reported being patched — including ones still being set up and not yet assigned to a team.</p>
                        <p>This can be higher than the "Never" slice of the freshness chart below, which only counts servers already assigned to a team.</p>
                    </flux:tooltip.content>
                </flux:tooltip>
            </div>
            <div class="text-4xl font-bold text-zinc-900 dark:text-zinc-100">{{ $neverCheckedInCount }}</div>
        </div>
    </div>

    <div class="mt-8">
        <flux:heading size="lg">Overdue servers</flux:heading>

        @if ($overdueServers->isEmpty())
            <div class="mt-4 rounded-lg bg-green-100 p-4 dark:bg-green-900/40">
                <flux:text class="text-green-900 dark:text-green-100">Nothing overdue. Everything's been patched within its window.</flux:text>
            </div>
        @else
            <flux:table class="">
                <flux:table.columns>
                    <flux:table.column>Server</flux:table.column>
                    <flux:table.column>Team</flux:table.column>
                    <flux:table.column>Overdue</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($overdueServers as $server)
                        <flux:table.row wire:key="overdue-{{ $server->id }}">
                            <flux:table.cell>
                                <flux:link :href="route('servers.show', $server)" wire:navigate>{{ $server->name }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $server->team->name }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($server->alerting_since)
                                    Alerting since {{ $server->alerting_since->format('j M Y') }}
                                @else
                                    {{ $server->daysOverdue() }} {{ \Illuminate\Support\Str::plural('day', $server->daysOverdue()) }} overdue
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    @if ($teamRows->isNotEmpty())
        <div class="mt-8">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">By team</flux:heading>
                <flux:radio.group wire:model.live="mode" variant="segmented" size="sm">
                    <flux:radio value="percent" label="Percent" />
                    <flux:radio value="absolute" label="Absolute" />
                </flux:radio.group>
            </div>

            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Team</flux:table.column>
                    <flux:table.column align="end">Overdue</flux:table.column>
                    <flux:table.column align="end">Silenced</flux:table.column>
                    <flux:table.column align="end">Patched 30d</flux:table.column>
                    <flux:table.column align="end">Total</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($teamRows as $row)
                        <flux:table.row wire:key="team-row-{{ $row['team']->id }}">
                            <flux:table.cell>{{ $row['team']->name }}</flux:table.cell>
                            @foreach (['overdue', 'silenced', 'patched_30d'] as $column)
                                <flux:table.cell align="end">
                                    @if ($row["{$column}_is_worst"])
                                        <flux:badge color="amber" size="sm">
                                            @if ($mode === 'percent')
                                                {{ $row["{$column}_pct"] }}%
                                            @else
                                                {{ $row[$column] }}
                                            @endif
                                        </flux:badge>
                                    @else
                                        @if ($mode === 'percent')
                                            {{ $row["{$column}_pct"] }}%
                                        @else
                                            {{ $row[$column] }}
                                        @endif
                                    @endif
                                </flux:table.cell>
                            @endforeach
                            <flux:table.cell align="end">{{ $row['total'] }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    @if ($totalCount > 0)
        <div class="mt-8">
            <flux:heading size="lg">Estate freshness</flux:heading>
            <flux:text class="mt-2">When every server was last patched.</flux:text>

            <div class="mt-4 flex h-6 w-full overflow-hidden rounded-lg">
                @foreach ($postureSegments as $segment)
                    @if ($postureBuckets[$segment['key']] > 0)
                        <div class="{{ $segment['colour'] }}"
                             style="width: {{ $postureBuckets[$segment['key']] / $totalCount * 100 }}%"
                             title="{{ $segment['label'] }}: {{ $postureBuckets[$segment['key']] }}"></div>
                    @endif
                @endforeach
            </div>

            <div class="mt-4 flex flex-col sm:flex-row flex-grow justify-end">
                @foreach ($postureSegments as $segment)
                    <div class="flex items-center gap-2 flex-1">
                        <span class="inline-block size-3 rounded-full {{ $segment['colour'] }}"></span>
                        <div>
                            <div class="text-sm text-zinc-700 dark:text-zinc-300">{{ $segment['label'] }}</div>
                            <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $postureBuckets[$segment['key']] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif


</div>
