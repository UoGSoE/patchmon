<?php

namespace App\Enums;

enum GraceUnit: string
{
    case Minutes = 'minutes';
    case Hours = 'hours';
    case Days = 'days';

    public function label(): string
    {
        return $this->name;
    }

    public function toMinutes(int $value): int
    {
        return match ($this) {
            self::Minutes => $value,
            self::Hours => $value * 60,
            self::Days => $value * 60 * 24,
        };
    }
}
