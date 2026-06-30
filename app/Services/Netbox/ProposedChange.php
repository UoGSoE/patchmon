<?php

namespace App\Services\Netbox;

use App\Enums\ChangeStatus;
use App\Enums\FlagReason;

readonly class ProposedChange
{
    public function __construct(
        public string $original,
        public ?string $proposed,
        public ChangeStatus $status,
        public ?FlagReason $reason = null,
        public ?string $proposedComments = null,
    ) {}
}
