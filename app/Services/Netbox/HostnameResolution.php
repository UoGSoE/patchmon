<?php

namespace App\Services\Netbox;

use App\Enums\HostnameResolutionStatus;

readonly class HostnameResolution
{
    /**
     * @param  array<int, string>  $resolved  Every candidate FQDN that resolved.
     */
    public function __construct(
        public HostnameResolutionStatus $status,
        public ?string $fqdn,
        public array $resolved,
    ) {}
}
