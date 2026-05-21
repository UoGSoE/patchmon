<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class RecordPatchEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $serverId,
        public ?string $sourceIp,
        public Carbon $at,
        public ?int $patchedBy = null,
        public ?string $notes = null,
    ) {}

    public function handle(): void
    {
        $server = Server::find($this->serverId);

        if (! $server) {
            return;
        }

        $patchedByUser = $this->patchedBy ? User::find($this->patchedBy) : null;

        $server->recordPatch($patchedByUser, $this->notes, $this->sourceIp, $this->at);
    }
}
