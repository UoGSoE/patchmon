<?php

namespace App\Listeners;

use App\Events\ActivityOccurred;
use App\Models\ActivityLog;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordActivity implements ShouldQueue
{
    public function handle(ActivityOccurred $event): void
    {
        $user = $event->userId ? User::find($event->userId) : null;
        $server = $event->serverId ? Server::find($event->serverId) : null;

        ActivityLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->full_name,
            'server_id' => $server?->id,
            'server_name' => $server?->name,
            'description' => $event->description,
            'source_ip' => $event->sourceIp,
        ]);
    }
}
