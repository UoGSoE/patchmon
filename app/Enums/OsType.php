<?php

namespace App\Enums;

enum OsType: string
{
    case Linux = 'linux';
    case Windows = 'windows';
    case Other = 'other';
    case NetboxUnknown = 'netbox_unknown';

    public function label(): string
    {
        return match ($this) {
            self::Linux => 'Linux',
            self::Windows => 'Windows',
            self::Other => 'Other',
            self::NetboxUnknown => 'Unknown',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::Linux => 'indigo',
            self::Windows => 'sky',
            self::Other => 'zinc',
            self::NetboxUnknown => 'amber',
        };
    }
}
