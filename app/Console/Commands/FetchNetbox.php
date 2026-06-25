<?php

namespace App\Console\Commands;

use App\Services\Netbox\NetboxClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('netbox:fetch')]
#[Description('Fetch the raw active server set from NetBox into a local gitignored fixture for offline cleanup work.')]
class FetchNetbox extends Command
{
    public function handle(): int
    {
        $raw = NetboxClient::make()->rawActiveServers();

        $fixture = [
            'fetched_at' => now()->toIso8601String(),
            'counts' => [
                'devices' => count($raw['devices']),
                'virtual_machines' => count($raw['virtual_machines']),
            ],
            ...$raw,
        ];

        Storage::disk('netbox')->put('servers.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Fetched {$fixture['counts']['devices']} devices and {$fixture['counts']['virtual_machines']} VMs into ".Storage::disk('netbox')->path('servers.json'));

        return self::SUCCESS;
    }
}
