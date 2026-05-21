<?php

namespace App\Enums;

enum OsType: string
{
    case Linux = 'linux';
    case Windows = 'windows';
    case Other = 'other';

    public function label(): string
    {
        return $this->name;
    }

    public function colour(): string
    {
        return match ($this) {
            self::Linux => 'amber',
            self::Windows => 'sky',
            self::Other => 'zinc',
        };
    }
}
