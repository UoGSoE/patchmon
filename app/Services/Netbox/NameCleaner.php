<?php

namespace App\Services\Netbox;

use App\Enums\ChangeStatus;
use App\Enums\FlagReason;
use App\Enums\HostnameResolutionStatus;

class NameCleaner
{
    public function __construct(private ?DnsResolver $resolver = null) {}

    /**
     * Propose a cleanup for every record in the set. A name shared by more than
     * one record is flagged for a human to rename — two machines can't own the
     * same name — and never auto-proposed; the rest go through the per-record
     * rules.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, ProposedChange>
     */
    public function proposals(array $records): array
    {
        $nameCounts = array_count_values(array_map(
            fn (array $record) => strtolower((string) data_get($record, 'name')),
            $records,
        ));

        return array_map(function (array $record) use ($nameCounts) {
            $name = (string) data_get($record, 'name');

            if (($nameCounts[strtolower($name)] ?? 0) > 1) {
                return new ProposedChange(
                    original: $name,
                    proposed: null,
                    status: ChangeStatus::Flag,
                    reason: FlagReason::NameCollision,
                );
            }

            return $this->propose($record);
        }, $records);
    }

    /**
     * Propose a cleanup for a single raw NetBox record, or flag it for a human.
     *
     * @param  array<string, mixed>  $record
     */
    public function propose(array $record): ProposedChange
    {
        $name = (string) data_get($record, 'name');

        if ($this->isPlaceholder(strtolower($name))) {
            return new ProposedChange(
                original: $name,
                proposed: null,
                status: ChangeStatus::Flag,
                reason: FlagReason::Placeholder,
            );
        }

        // A trailing "(annotation)" is noise in the name; move it to comments.
        $base = $name;
        $annotation = null;
        if (preg_match('/^(.*?)\s*\((.+)\)\s*$/', $name, $matches) === 1) {
            $base = trim($matches[1]);
            $annotation = trim($matches[2]);
        }

        $cleaned = strtolower($base);

        if (($hostDept = $this->resolveHostDept($cleaned)) !== null) {
            return new ProposedChange(
                original: $name,
                proposed: $hostDept.'.'.config('patchmon.netbox.default_domain'),
                status: ChangeStatus::Propose,
                proposedComments: $annotation === null ? null : $this->appendComment($record, $annotation),
            );
        }

        if ($this->hasSingleDot($cleaned)) {
            return new ProposedChange(
                original: $name,
                proposed: null,
                status: ChangeStatus::Flag,
                reason: FlagReason::UnknownDepartment,
            );
        }

        if ($this->isBare($cleaned)) {
            return $this->resolveBare($cleaned, $name, $record, $annotation);
        }

        if ($this->isValidHostname($cleaned)) {
            return new ProposedChange(
                original: $name,
                proposed: $cleaned,
                status: $cleaned === $name ? ChangeStatus::Unchanged : ChangeStatus::Propose,
            );
        }

        return new ProposedChange(
            original: $name,
            proposed: null,
            status: ChangeStatus::Flag,
            reason: FlagReason::UnclearName,
        );
    }

    /**
     * A structurally valid multi-label hostname: dot-separated DNS labels and
     * nothing else. A name that falls this far and still looks like one (a
     * multi-label FQDN already in good shape) is left alone; anything carrying
     * spaces, underscores or other characters we can't safely rewrite into a
     * hostname is flagged for a human.
     */
    private function isValidHostname(string $name): bool
    {
        return preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $name) === 1;
    }

    /**
     * A bare hostname: a single DNS label with no department to read off the
     * name, so we recover the department by resolving it against DNS.
     */
    private function isBare(string $name): bool
    {
        return preg_match('/^[a-z0-9-]+$/', $name) === 1;
    }

    /**
     * Resolve a bare hostname against the subdomains its building suggests,
     * proposing the FQDN on a unique hit and flagging anything we can't pin
     * down to a single department.
     *
     * @param  array<string, mixed>  $record
     */
    private function resolveBare(string $host, string $name, array $record, ?string $annotation): ProposedChange
    {
        $resolution = $this->resolver()->resolveBareHostname(
            $host,
            $this->candidateSubdomains($record),
            config('patchmon.netbox.default_domain'),
        );

        if ($resolution->status === HostnameResolutionStatus::Accepted) {
            return new ProposedChange(
                original: $name,
                proposed: $resolution->fqdn,
                status: ChangeStatus::Propose,
                proposedComments: $annotation === null ? null : $this->appendComment($record, $annotation),
            );
        }

        return new ProposedChange(
            original: $name,
            proposed: null,
            status: ChangeStatus::Flag,
            reason: $resolution->status === HostnameResolutionStatus::Ambiguous
                ? FlagReason::AmbiguousHostname
                : FlagReason::UnresolvedHostname,
        );
    }

    /**
     * The subdomains worth trying for a bare hostname, narrowed by its building:
     * a single-department building is a safe prior; anything else (shared
     * buildings, data centres, no site) falls back to the full set.
     *
     * @param  array<string, mixed>  $record
     * @return array<int, string>
     */
    private function candidateSubdomains(array $record): array
    {
        $site = (string) data_get($record, 'site.name');
        $buildings = config('patchmon.netbox.building_departments', []);

        return $buildings[$site] ?? config('patchmon.netbox.subdomains');
    }

    private function resolver(): DnsResolver
    {
        return $this->resolver ??= DnsResolver::make();
    }

    /**
     * The existing comments with the stripped annotation appended on the end.
     *
     * @param  array<string, mixed>  $record
     */
    private function appendComment(array $record, string $annotation): string
    {
        $existing = trim((string) data_get($record, 'comments', ''));

        return trim($existing.' '.$annotation);
    }

    /**
     * A placeholder name standing in for kit nobody has identified yet
     * ("Unlabeled R3 5", "Unlabled 4", "Unidentified 1") — never guessed at.
     */
    private function isPlaceholder(string $name): bool
    {
        return str_contains($name, 'unlab') || str_contains($name, 'unident');
    }

    /**
     * If the name is "host.dept" where dept is a known subdomain — or a known
     * alias for one (e.g. "cognition" -> "cose") — return the "host.dept" name
     * with the dept token rewritten to its canonical subdomain. Returns null
     * for any other shape, leaving an unrecognised single-dot token to fall
     * through to the unknown-department flag.
     */
    private function resolveHostDept(string $name): ?string
    {
        if (preg_match('/^([a-z0-9-]+)\.([a-z0-9-]+)$/', $name, $matches) !== 1) {
            return null;
        }

        [, $host, $token] = $matches;

        if (in_array($token, config('patchmon.netbox.subdomains'), true)) {
            return $name;
        }

        $alias = config('patchmon.netbox.department_aliases', [])[$token] ?? null;

        return $alias === null ? null : "{$host}.{$alias}";
    }

    /**
     * A single-dot "host.token" name with valid DNS labels, where the token is
     * one we don't recognise as a subdomain (resolveHostDept() having returned
     * null). A garbled host label — spaces and the like — does not count; that
     * falls through to be flagged as an unclear name instead.
     */
    private function hasSingleDot(string $name): bool
    {
        return preg_match('/^[a-z0-9-]+\.[a-z0-9-]+$/', $name) === 1;
    }
}
