<x-mail::message>
# {{ $server->name }} hasn't been patched

Patchmon expected **{{ $server->name }}** to be patched by now and hasn't heard from it.

@if ($server->cron_expression)
**Schedule:** `{{ $server->cron_expression }}` (cron)
@else
**Schedule:** {{ $server->schedule_frequency }} × {{ strtolower($server->schedule_interval->label()) }}
@endif

**Grace period:** {{ $server->grace_value }} {{ strtolower($server->grace_units->label()) }}

@if ($server->last_patched_at)
**Last patched:** {{ $server->last_patched_at->toDayDateTimeString() }} ({{ $server->last_patched_at->diffForHumans() }})
@else
**Last patched:** never
@endif

@if ($server->alerting_since)
**Awol since:** {{ $server->alerting_since->toDayDateTimeString() }} ({{ $server->alerting_since->diffForHumans() }})
@endif

<x-mail::button :url="route('home')">
View in Patchmon
</x-mail::button>

@if ($server->team)
If this isn't your problem, the **{{ $server->team->name }}** team owns this server.
@else
This server belongs to {{ $server->user->full_name ?: $server->user->email }}.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
