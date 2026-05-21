<?php

namespace App\Jobs;

use App\Models\Server;
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
    ) {}

    public function handle(): void
    {
        $server = Server::find($this->serverId);

        if (! $server) {
            return;
        }

        $server->recordPatchEvent($this->sourceIp, $this->at);
    }
}
