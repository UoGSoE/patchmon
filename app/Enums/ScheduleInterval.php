<?php

namespace App\Enums;

enum ScheduleInterval: string
{
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return $this->name;
    }

    public function colour(): string
    {
        return match ($this) {
            self::Hourly => 'rose',
            self::Daily => 'amber',
            self::Weekly => 'sky',
            self::Monthly => 'violet',
        };
    }

    public function toMinutes(): int
    {
        return match ($this) {
            self::Hourly => 60,
            self::Daily => 60 * 24,
            self::Weekly => 60 * 24 * 7,
            self::Monthly => 60 * 24 * 30,
        };
    }
}
