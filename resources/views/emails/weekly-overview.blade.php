<x-mail::message>
# Weekly patching overview

@if ($overdueCount > 0)
**{{ $overdueCount }} {{ Str::plural('server', $overdueCount) }} {{ $overdueCount === 1 ? 'is' : 'are' }} overdue.**
@else
**All servers are up to date.**
@endif

- **{{ $totalCount }}** monitored
- **{{ $overdueCount }}** overdue
- **{{ $silencedCount }}** silenced
- **{{ $patchedRecentlyCount }}** patched in the last 30 days

@if ($overdueCount > 0)
<x-mail::table>
| Server | Team | Overdue by |
| :----- | :--- | :--------- |
@foreach ($overdueServers->take(5) as $server)
| {{ $server->name }} | {{ $server->team->name }} | {{ $server->deadline()->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE) }} |
@endforeach
</x-mail::table>

@if ($overdueCount > 5)
…and {{ $overdueCount - 5 }} {{ Str::plural('other', $overdueCount - 5) }}.
@endif
@endif

<x-mail::button :url="route('admin.dashboard')">
View the dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
