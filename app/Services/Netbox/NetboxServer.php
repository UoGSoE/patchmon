<?php

namespace App\Services\Netbox;

use App\Enums\OsType;

readonly class NetboxServer
{
    public function __construct(
        public int $netboxId,
        public bool $isVirtual,
        public string $name,
        public OsType $osType,
    ) {}
}
