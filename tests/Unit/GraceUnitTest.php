<?php

use App\Enums\GraceUnit;

it('returns the expected label', function (GraceUnit $unit, string $label) {
    expect($unit->label())->toBe($label);
})->with([
    [GraceUnit::Minutes, 'Minutes'],
    [GraceUnit::Hours, 'Hours'],
    [GraceUnit::Days, 'Days'],
]);

it('converts a value to minutes', function (GraceUnit $unit, int $value, int $expectedMinutes) {
    expect($unit->toMinutes($value))->toBe($expectedMinutes);
})->with([
    'minutes pass through' => [GraceUnit::Minutes, 30, 30],
    '2 hours is 120 minutes' => [GraceUnit::Hours, 2, 120],
    '1 day is 1440 minutes' => [GraceUnit::Days, 1, 1440],
    'zero is zero' => [GraceUnit::Hours, 0, 0],
]);
