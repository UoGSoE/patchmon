<?php

namespace App\Enums;

enum IpCheck: string
{
    case Match = 'match';
    case Mismatch = 'mismatch';
    case NoNetboxIp = 'no_netbox_ip';
    case Ipv6Unverifiable = 'ipv6_unverifiable';
    case Unverified = 'unverified';

    public function label(): string
    {
        return match ($this) {
            self::Match => 'NetBox IP matches DNS',
            self::Mismatch => 'NetBox IP does not match DNS',
            self::NoNetboxIp => 'No IP recorded in NetBox',
            self::Ipv6Unverifiable => 'NetBox IP is IPv6 (not checked)',
            self::Unverified => 'NetBox IP not checked (name did not resolve)',
        };
    }
}
