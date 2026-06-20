<x-mail::message>
# Servers awaiting team allocation

{{ $servers->count() }} {{ Str::plural('server', $servers->count()) }} {{ $servers->count() === 1 ? 'has' : 'have' }} been sitting unassigned for more than a week. Until they're allocated to a team, nobody is being alerted about their patching.

<x-mail::table>
| Server | Waiting since |
| :----- | :------------ |
@foreach ($servers as $server)
| {{ $server->name }} | {{ $server->created_at->toFormattedDateString() }} ({{ $server->created_at->diffForHumans() }}) |
@endforeach
</x-mail::table>

<x-mail::button :url="route('home')">
View in Patchmon
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
