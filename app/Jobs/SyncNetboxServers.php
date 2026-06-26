<?php

namespace App\Jobs;

use App\Events\ActivityOccurred;
use App\Models\Server;
use App\Rules\Fqdn;
use App\Services\Netbox\NetboxClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
            'invalid' => [],
            'inactive_sweep_skipped' => false,
        ];

        $seen = [];

        $netboxServers = $client->activeServers();

        // Snapshot what we currently believe is active, so the inactive sweep below
        // can sanity-check the response size before trusting it (see ait kOKT9.8).
        $knownActiveBefore = Server::query()->whereNotNull('netbox_id')->whereNull('inactive_since')->count();

        foreach ($netboxServers as $netboxServer) {
            $seen[] = $this->key($netboxServer->netboxId, $netboxServer->isVirtual);

            $existing = Server::query()
                ->where('netbox_id', $netboxServer->netboxId)
                ->where('is_virtual', $netboxServer->isVirtual)
                ->first();

            if (! $this->isValidHostname($netboxServer->name)) {
                $summary['invalid'][] = $netboxServer->name;

                continue;
            }

            if ($this->hasNameConflict($netboxServer->name, $existing)) {
                $summary['conflicts'][] = $netboxServer->name;

                continue;
            }

            if ($existing) {
                $wasInactive = $existing->isInactive();

                $existing->update([
                    'name' => $netboxServer->name,
                    'os_type' => $netboxServer->osType,
                    'is_virtual' => $netboxServer->isVirtual,
                    'inactive_since' => null,
                ]);

                if ($existing->wasChanged()) {
                    ActivityOccurred::dispatch(
                        null,
                        $existing->id,
                        $wasInactive ? 'Server reactivated from NetBox' : 'Server updated from NetBox',
                    );
                }

                $wasInactive ? $summary['reactivated']++ : $summary['updated']++;

                continue;
            }

            $created = Server::create([
                'team_id' => null,
                'created_by_user_id' => null,
                'netbox_id' => $netboxServer->netboxId,
                'is_virtual' => $netboxServer->isVirtual,
                'name' => $netboxServer->name,
                'os_type' => $netboxServer->osType,
                'interval_months' => config('patchmon.triage_defaults.interval_months'),
                'grace_value' => config('patchmon.triage_defaults.grace_value'),
                'grace_units' => config('patchmon.triage_defaults.grace_units'),
            ]);

            ActivityOccurred::dispatch(null, $created->id, 'Server discovered from NetBox');

            $summary['created']++;
        }

        if ($this->responseIsPlausible(count($netboxServers), $knownActiveBefore)) {
            $summary['inactive'] = $this->flagInactive($seen);
        } else {
            $summary['inactive_sweep_skipped'] = true;
            Log::warning('NetBox sync: skipped the inactive sweep — implausibly small active set', [
                'fetched' => count($netboxServers),
                'known_active' => $knownActiveBefore,
                'change_ratio' => (float) config('patchmon.netbox.change_ratio'),
            ]);
        }

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

                ActivityOccurred::dispatch(null, $server->id, 'Server marked inactive (left NetBox)');

                $count++;
            });

        return $count;
    }

    /**
     * NetBox names are free-text and often aren't valid hostnames — bare labels,
     * rack placeholders, embedded notes. We import the clean ones verbatim and
     * report the rest for NetBox-side cleanup rather than storing junk.
     */
    private function isValidHostname(string $name): bool
    {
        return Validator::make(['name' => $name], ['name' => [new Fqdn]])->passes();
    }

    /**
     * Names are stored lower-cased, so compare case-insensitively. When refreshing an
     * existing synced server we exclude its own row, so a server keeps its current name
     * without colliding with itself.
     */
    private function hasNameConflict(string $name, ?Server $existing = null): bool
    {
        $query = Server::query()->where('name', strtolower($name));

        if ($existing) {
            $query->whereKeyNot($existing->id);
        }

        return $query->exists();
    }

    /**
     * Guard against partial NetBox responses — an early-truncated page, a filter or
     * permission change — before the destructive inactive sweep. If NetBox reports
     * far fewer active servers than we currently track, we don't trust it enough to
     * flag the rest inactive and clear their alerting. The first sync (nothing tracked
     * yet) is always plausible; the threshold scales with the estate via change_ratio.
     */
    private function responseIsPlausible(int $fetched, int $knownActive): bool
    {
        if ($knownActive === 0) {
            return true;
        }

        return $fetched >= (float) config('patchmon.netbox.change_ratio') * $knownActive;
    }

    private function key(int $netboxId, bool $isVirtual): string
    {
        return $netboxId.'-'.($isVirtual ? '1' : '0');
    }
}
