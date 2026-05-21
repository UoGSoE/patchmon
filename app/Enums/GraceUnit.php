<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum GraceUnit: string
{
    case Days = 'days';
    case Weeks = 'weeks';
    case Months = 'months';

    public function label(): string
    {
        return $this->name;
    }

    public function colour(): string
    {
        return match ($this) {
            self::Days => 'amber',
            self::Weeks => 'sky',
            self::Months => 'violet',
        };
    }

    public function addTo(Carbon $when, int $value): Carbon
    {
        return match ($this) {
            self::Days => $when->copy()->addDays($value),
            self::Weeks => $when->copy()->addWeeks($value),
            self::Months => $when->copy()->addMonthsNoOverflow($value),
        };
    }
}
