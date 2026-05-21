<?php

use App\Enums\GraceUnit;
use Illuminate\Support\Carbon;

it('returns the expected label', function (GraceUnit $unit, string $label) {
    expect($unit->label())->toBe($label);
})->with([
    [GraceUnit::Days, 'Days'],
    [GraceUnit::Weeks, 'Weeks'],
    [GraceUnit::Months, 'Months'],
]);

it('adds the right amount of time to a Carbon instance', function (GraceUnit $unit, int $value, Carbon $start, Carbon $expected) {
    expect($unit->addTo($start, $value)->equalTo($expected))->toBeTrue();
})->with([
    '7 days adds a week' => [GraceUnit::Days, 7, Carbon::parse('2026-05-01'), Carbon::parse('2026-05-08')],
    '2 weeks adds 14 days' => [GraceUnit::Weeks, 2, Carbon::parse('2026-05-01'), Carbon::parse('2026-05-15')],
    '1 month adds a calendar month' => [GraceUnit::Months, 1, Carbon::parse('2026-05-15'), Carbon::parse('2026-06-15')],
    'month-end rolls sensibly' => [GraceUnit::Months, 1, Carbon::parse('2026-01-31'), Carbon::parse('2026-02-28')],
]);

it('does not mutate the source Carbon instance', function () {
    $start = Carbon::parse('2026-05-15');
    GraceUnit::Days->addTo($start, 10);

    expect($start->equalTo(Carbon::parse('2026-05-15')))->toBeTrue();
});
