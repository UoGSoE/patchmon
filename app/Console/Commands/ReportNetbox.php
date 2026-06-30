<?php

namespace App\Console\Commands;

use App\Enums\ChangeStatus;
use App\Services\Netbox\DnsResolver;
use App\Services\Netbox\NameCleaner;
use App\Services\Netbox\ProposalReport;
use App\Services\Netbox\ProposalValidator;
use App\Services\Netbox\ValidatedChange;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('netbox:report')]
#[Description('Generate a Markdown proposed-changes report from the cached NetBox set for sysadmin review. Read-only; re-derives off the warm DNS cache and writes nothing back to NetBox.')]
class ReportNetbox extends Command
{
    public function handle(DnsResolver $resolver): int
    {
        $disk = Storage::disk('netbox');

        if (! $disk->exists('servers.json')) {
            $this->error('No fixture found — run netbox:fetch first (on the VPN).');

            return self::FAILURE;
        }

        $fixture = json_decode($disk->get('servers.json'), true);
        $records = [...$fixture['devices'] ?? [], ...$fixture['virtual_machines'] ?? []];

        $proposals = (new NameCleaner($resolver))->proposals($records);
        $validated = (new ProposalValidator($resolver))->validate($proposals, $records);

        $disk->put('proposed-changes.md', (new ProposalReport)->render($validated, $records));

        $this->summarise($records, $validated);
        $this->info('Wrote proposed-changes report to '.$disk->path('proposed-changes.md'));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, ValidatedChange>  $validated
     */
    private function summarise(array $records, array $validated): void
    {
        $count = fn (callable $matches) => count(array_filter($validated, $matches));

        $ready = $count(fn (ValidatedChange $v) => $v->change->status === ChangeStatus::Propose && $v->resolves === true);
        $unverified = $count(fn (ValidatedChange $v) => $v->change->status === ChangeStatus::Propose && $v->resolves === false);
        $flagged = $count(fn (ValidatedChange $v) => $v->change->status === ChangeStatus::Flag);
        $unchanged = $count(fn (ValidatedChange $v) => $v->change->status === ChangeStatus::Unchanged);

        $this->info(count($records)." NetBox records: {$ready} ready, {$unverified} unverified, {$flagged} flagged, {$unchanged} unchanged.");
    }
}
