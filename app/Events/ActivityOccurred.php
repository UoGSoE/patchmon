<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ActivityOccurred
{
    use Dispatchable;

    public function __construct(
        public ?int $userId,
        public ?int $serverId,
        public string $description,
        public ?string $sourceIp = null,
    ) {}
}
