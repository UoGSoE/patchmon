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
}
