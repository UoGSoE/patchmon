<x-mail::message>
# {{ $job->name }} hasn't checked in

Cronmon expected **{{ $job->name }}** to check in by now and hasn't heard from it.

@if ($job->cron_expression)
**Schedule:** `{{ $job->cron_expression }}` (cron)
@else
**Schedule:** {{ $job->schedule_frequency }} × {{ strtolower($job->schedule_interval->label()) }}
@endif

**Grace period:** {{ $job->grace_value }} {{ strtolower($job->grace_units->label()) }}

@if ($job->last_checked_in_at)
**Last check-in:** {{ $job->last_checked_in_at->toDayDateTimeString() }} ({{ $job->last_checked_in_at->diffForHumans() }})
@else
**Last check-in:** never
@endif

@if ($job->alerting_since)
**Awol since:** {{ $job->alerting_since->toDayDateTimeString() }} ({{ $job->alerting_since->diffForHumans() }})
@endif

<x-mail::button :url="route('home')">
View in Cronmon
</x-mail::button>

@if ($job->team)
If this isn't your problem, the **{{ $job->team->name }}** team owns this job.
@else
This job belongs to {{ $job->user->name ?? $job->user->email }}.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
