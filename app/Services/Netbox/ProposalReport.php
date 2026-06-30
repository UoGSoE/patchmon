<?php

namespace App\Services\Netbox;

use App\Enums\ChangeStatus;
use App\Enums\FlagReason;
use App\Enums\IpCheck;

class ProposalReport
{
    /**
     * Render the validated proposals as a Markdown report for sysadmin review.
     * Read-only artefact — nothing here writes to NetBox.
     *
     * @param  array<int, ValidatedChange>  $validated
     * @param  array<int, array<string, mixed>>  $records
     */
    public function render(array $validated, array $records): string
    {
        $sections = [
            $this->header(),
            $this->summary($validated),
            $this->readyToApply($validated),
            $this->unverified($validated),
            $this->ipMismatches($validated, $records),
            $this->flagged($validated, $records),
        ];

        return implode("\n\n", array_filter($sections))."\n";
    }

    private function header(): string
    {
        return implode("\n", [
            '# NetBox proposed changes',
            '',
            'Read-only — nothing has been written to NetBox. Names below are cleaned',
            'hostnames; where a name carried extra detail in brackets, that note is',
            "proposed for the record's NetBox comments field rather than dropped. Flagged",
            'records that may carry personal data are referenced by NetBox ID and link only.',
        ]);
    }

    /**
     * @param  array<int, ValidatedChange>  $validated
     */
    private function readyToApply(array $validated): string
    {
        $proposes = $this->proposes($validated, resolves: true);

        if ($proposes === []) {
            return '';
        }

        $lines = ['## Ready to apply', '', 'Cleaned and confirmed in DNS — safe to write back.', ''];

        foreach ($proposes as $v) {
            $lines[] = '- `'.$v->change->original.'` → `'.$v->change->proposed.'`';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, ValidatedChange>  $validated
     */
    private function summary(array $validated): string
    {
        $ip = fn (IpCheck $check) => count(array_filter($validated, fn (ValidatedChange $v) => $v->ipCheck === $check));

        return implode("\n", [
            '## Summary',
            '',
            '| Outcome | Count |',
            '|---|---:|',
            '| Records reviewed | '.count($validated).' |',
            '| Ready to apply | '.count($this->proposes($validated, resolves: true)).' |',
            '| Proposed but unverified | '.count($this->proposes($validated, resolves: false)).' |',
            '| Flagged for manual cleanup | '.count($this->withStatus($validated, ChangeStatus::Flag)).' |',
            '| Already valid (unchanged) | '.count($this->withStatus($validated, ChangeStatus::Unchanged)).' |',
            '',
            'IP cross-check: '.$ip(IpCheck::Match).' match, '.$ip(IpCheck::Mismatch).' mismatch, '.$ip(IpCheck::Unverified).' unverified (the rest have no NetBox IP).',
        ]);
    }

    /**
     * Proposals whose cleaned name does not resolve in DNS. Split into the ones
     * we expect not to resolve — aliased departments (the cognition cluster
     * isn't in campus DNS) — and the rest, which are worth a look as possible
     * hostname typos.
     *
     * @param  array<int, ValidatedChange>  $validated
     */
    private function unverified(array $validated): string
    {
        $unverified = $this->proposes($validated, resolves: false);

        if ($unverified === []) {
            return '';
        }

        $aliases = config('patchmon.netbox.department_aliases', []);
        $expected = array_filter($unverified, fn (ValidatedChange $v) => $this->isAliased($v->change->original, $aliases));
        $investigate = array_filter($unverified, fn (ValidatedChange $v) => ! $this->isAliased($v->change->original, $aliases));

        $lines = ['## Proposed but unverified', '', 'Cleaned, but the proposed name does not resolve in DNS.'];

        if ($expected !== []) {
            $lines[] = '';
            $lines[] = '### Expected — aliased department, host not in campus DNS ('.count($expected).')';
            $lines[] = '';
            foreach ($expected as $v) {
                $lines[] = '- `'.$v->change->original.'` → `'.$v->change->proposed.'`';
            }
        }

        if ($investigate !== []) {
            $lines[] = '';
            $lines[] = '### Investigate — possible typo ('.count($investigate).')';
            $lines[] = '';
            foreach ($investigate as $v) {
                $lines[] = '- `'.$v->change->original.'` → `'.$v->change->proposed.'`';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Records whose NetBox primary IP doesn't match where the name resolves —
     * likely a stale NetBox record. Referenced by the safe proposed name where
     * there is one, otherwise by NetBox ID and link only (a flagged record's
     * original name may be personal data).
     *
     * @param  array<int, ValidatedChange>  $validated
     * @param  array<int, array<string, mixed>>  $records
     */
    private function ipMismatches(array $validated, array $records): string
    {
        $mismatches = array_filter($validated, fn (ValidatedChange $v) => $v->ipCheck === IpCheck::Mismatch);

        if ($mismatches === []) {
            return '';
        }

        $lines = ['## IP mismatches', '', "NetBox's recorded IP doesn't match where the name resolves — likely stale.", ''];

        foreach ($mismatches as $i => $v) {
            $address = explode('/', (string) data_get($records[$i], 'primary_ip.address'))[0];
            $label = $v->change->proposed !== null ? '`'.$v->change->proposed.'`' : 'NetBox #'.$records[$i]['id'];
            $lines[] = '- '.$label.' — NetBox has '.$address.' ('.$this->uiUrl($records[$i]['url']).')';
        }

        return implode("\n", $lines);
    }

    /**
     * Whether a "host.dept" name's department token is a configured alias — the
     * signal that a non-resolving proposal is expected (aliased) rather than a
     * typo to chase.
     *
     * @param  array<string, string>  $aliases
     */
    private function isAliased(string $original, array $aliases): bool
    {
        return preg_match('/^[^.]+\.([^.]+)$/', strtolower($original), $matches) === 1
            && array_key_exists($matches[1], $aliases);
    }

    /**
     * The flagged records, grouped by reason. Unclear names may carry personal
     * data (a host can be named after the person who owns it), so those are
     * referenced by NetBox ID and link only — never by name. Every other flag
     * reason is hostname- or placeholder-shaped and safe to show.
     *
     * @param  array<int, ValidatedChange>  $validated
     * @param  array<int, array<string, mixed>>  $records
     */
    private function flagged(array $validated, array $records): string
    {
        $flags = $this->withStatus($validated, ChangeStatus::Flag);

        if ($flags === []) {
            return '';
        }

        $lines = ['## Flagged for manual cleanup', '', 'These need a human — never auto-changed.'];

        foreach (FlagReason::cases() as $reason) {
            $forReason = array_filter($flags, fn (ValidatedChange $v) => $v->change->reason === $reason);

            if ($forReason === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = '### '.$reason->label().' ('.count($forReason).')';
            $lines[] = '';

            foreach ($forReason as $i => $v) {
                $lines[] = $reason === FlagReason::UnclearName
                    ? '- NetBox #'.$records[$i]['id'].' ('.$this->uiUrl($records[$i]['url']).')'
                    : '- `'.$v->change->original.'`';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * The browsable NetBox URL for a record, from its API URL.
     */
    private function uiUrl(string $apiUrl): string
    {
        return str_replace('/api/', '/', $apiUrl);
    }

    /**
     * @param  array<int, ValidatedChange>  $validated
     * @return array<int, ValidatedChange>
     */
    private function withStatus(array $validated, ChangeStatus $status): array
    {
        return array_filter($validated, fn (ValidatedChange $v) => $v->change->status === $status);
    }

    /**
     * @param  array<int, ValidatedChange>  $validated
     * @return array<int, ValidatedChange>
     */
    private function proposes(array $validated, bool $resolves): array
    {
        return array_filter(
            $validated,
            fn (ValidatedChange $v) => $v->change->status === ChangeStatus::Propose && $v->resolves === $resolves,
        );
    }
}
