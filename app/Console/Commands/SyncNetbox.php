<?php

namespace App\Console\Commands;

use App\Jobs\SyncNetboxServers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('patchmon:sync-netbox')]
#[Description('Refresh the server list from NetBox by dispatching the sync job.')]
class SyncNetbox extends Command
{
    public function handle(): int
    {
        SyncNetboxServers::dispatch();

        return self::SUCCESS;
    }
}
