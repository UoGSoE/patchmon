<?php

namespace App\Enums;

enum FlagReason: string
{
    case Placeholder = 'placeholder';
    case UnresolvedHostname = 'unresolved_hostname';
    case AmbiguousHostname = 'ambiguous_hostname';
    case UnknownDepartment = 'unknown_department';
    case NameCollision = 'name_collision';
    case UnclearName = 'unclear_name';

    public function label(): string
    {
        return match ($this) {
            self::Placeholder => 'Placeholder name',
            self::UnresolvedHostname => 'Bare hostname did not resolve',
            self::AmbiguousHostname => 'Bare hostname resolved under more than one department',
            self::UnknownDepartment => 'Department token is not a known subdomain',
            self::NameCollision => 'Name is shared by more than one record',
            self::UnclearName => 'Name is not a recognisable hostname',
        };
    }
}
