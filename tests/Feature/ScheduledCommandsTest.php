<?php

use Illuminate\Console\Scheduling\Schedule;

it('schedules patchmon:evaluate to run once daily at 08:40', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'patchmon:evaluate'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('40 8 * * *');
});

it('schedules patchmon:sync-netbox to run once daily at 08:10', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'patchmon:sync-netbox'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('10 8 * * *');
});
