<?php

use Illuminate\Console\Scheduling\Schedule;

it('schedules cronmon:evaluate to run every minute', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'cronmon:evaluate'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('* * * * *');
});
