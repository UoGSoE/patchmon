<?php

use App\Enums\ScheduleInterval;

it('returns the expected label', function (ScheduleInterval $interval, string $label) {
    expect($interval->label())->toBe($label);
})->with([
    [ScheduleInterval::Hourly, 'Hourly'],
    [ScheduleInterval::Daily, 'Daily'],
    [ScheduleInterval::Weekly, 'Weekly'],
    [ScheduleInterval::Monthly, 'Monthly'],
]);

it('returns the expected colour', function (ScheduleInterval $interval, string $colour) {
    expect($interval->colour())->toBe($colour);
})->with([
    [ScheduleInterval::Hourly, 'rose'],
    [ScheduleInterval::Daily, 'amber'],
    [ScheduleInterval::Weekly, 'sky'],
    [ScheduleInterval::Monthly, 'violet'],
]);
