<x-mail::message>
# {{ $server->name }} hasn't been patched

Patchmon expected **{{ $server->name }}** to be patched by now, but no recent patch event has been recorded.

**Schedule:** {{ $server->intervalLabel() }}

**Grace period:** {{ $server->grace_value }} {{ strtolower($server->grace_units->label()) }}

@if ($server->last_patched_at)
**Last patched:** {{ $server->last_patched_at->toDayDateTimeString() }} ({{ $server->last_patched_at->diffForHumans() }})
@else
**Last patched:** never
@endif

@if ($server->alerting_since)
**Overdue since:** {{ $server->alerting_since->toDayDateTimeString() }} ({{ $server->alerting_since->diffForHumans() }})
@endif

<x-mail::button :url="route('home')">
View in Patchmon
</x-mail::button>

The **{{ $server->team->name }}** team owns this server.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
