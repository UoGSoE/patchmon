<?php

namespace App\Enums;

enum HostnameResolutionStatus: string
{
    case Accepted = 'accepted';
    case NoMatch = 'no_match';
    case Ambiguous = 'ambiguous';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Accepted',
            self::NoMatch => 'No match',
            self::Ambiguous => 'Ambiguous',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::Accepted => 'emerald',
            self::NoMatch => 'zinc',
            self::Ambiguous => 'amber',
        };
    }
}
