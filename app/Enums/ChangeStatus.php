<?php

namespace App\Enums;

enum ChangeStatus: string
{
    case Propose = 'propose';
    case Flag = 'flag';
    case Unchanged = 'unchanged';

    public function label(): string
    {
        return match ($this) {
            self::Propose => 'Propose change',
            self::Flag => 'Flag for review',
            self::Unchanged => 'No change',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::Propose => 'sky',
            self::Flag => 'amber',
            self::Unchanged => 'zinc',
        };
    }
}
