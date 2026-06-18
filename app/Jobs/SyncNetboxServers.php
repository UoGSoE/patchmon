<?php

namespace App\Jobs;

use App\Models\Server;
use App\Services\Netbox\NetboxClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncNetboxServers implements ShouldQueue
{
    use Queueable;

    /**
     * The scheduled command and the staff "Refresh now" button both dispatch this
     * job, so lock it against itself — a second run is dropped rather than queued.
     * The lock expires after ten minutes so a worker killed mid-sync can't wedge it
     * and silently block every future run.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('netbox-sync'))->dontRelease()->expireAfter(600)];
    }

    public function handle(NetboxClient $client): void
    {
        $summary = [
            'created' => 0,
            'updated' => 0,
            'reactivated' => 0,
            'inactive' => 0,
            'conflicts' => [],
        ];

        $seen = [];

        foreach ($client->activeServers() as $netboxServer) {
            $seen[] = $this->key($netboxServer->netboxId, $netboxServer->isVirtual);

            $existing = Server::query()
                ->where('netbox_id', $netboxServer->netboxId)
                ->where('is_virtual', $netboxServer->isVirtual)
                ->first();

            if ($existing) {
                $wasInactive = $existing->isInactive();

                $existing->update([
                    'name' => $netboxServer->name,
                    'os_type' => $netboxServer->osType,
                    'is_virtual' => $netboxServer->isVirtual,
                    'inactive_since' => null,
                ]);

                $wasInactive ? $summary['reactivated']++ : $summary['updated']++;

                continue;
            }

            if (Server::query()->where('name', strtolower($netboxServer->name))->exists()) {
                $summary['conflicts'][] = $netboxServer->name;

                continue;
            }

            Server::create([
                'team_id' => null,
                'created_by_user_id' => null,
                'netbox_id' => $netboxServer->netboxId,
                'is_virtual' => $netboxServer->isVirtual,
                'name' => $netboxServer->name,
                'os_type' => $netboxServer->osType,
                'interval_months' => config('patchmon.netbox.default_interval_months'),
                'grace_value' => config('patchmon.netbox.default_grace_value'),
                'grace_units' => config('patchmon.netbox.default_grace_units'),
            ]);

            $summary['created']++;
        }

        $summary['inactive'] = $this->flagInactive($seen);
        $summary['ran_at'] = now()->toIso8601String();

        Cache::put('netbox.last_sync_summary', $summary);
        Log::info('NetBox sync complete', $summary);
    }

    /**
     * Stamp NetBox-sourced servers that have dropped out of NetBox's active set,
     * clearing their alerting state. Servers never sourced from NetBox are left
     * untouched, as are ones already flagged inactive.
     *
     * @param  array<int, string>  $seen
     */
    private function flagInactive(array $seen): int
    {
        $count = 0;

        Server::query()
            ->whereNotNull('netbox_id')
            ->whereNull('inactive_since')
            ->get()
            ->each(function (Server $server) use ($seen, &$count): void {
                if (in_array($this->key($server->netbox_id, $server->is_virtual), $seen, true)) {
                    return;
                }

                $server->update([
                    'inactive_since' => now(),
                    'alerting_since' => null,
                    'last_alerted_at' => null,
                ]);

                $count++;
            });

        return $count;
    }

    private function key(int $netboxId, bool $isVirtual): string
    {
        return $netboxId.'-'.($isVirtual ? '1' : '0');
    }
}
