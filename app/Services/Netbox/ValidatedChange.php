<?php

namespace App\Services\Netbox;

use App\Enums\IpCheck;

readonly class ValidatedChange
{
    public function __construct(
        public ProposedChange $change,
        public ?bool $resolves,
        public IpCheck $ipCheck,
    ) {}
}
