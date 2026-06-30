<?php

namespace App\Services\Netbox;

use App\Enums\IpCheck;

class ProposalValidator
{
    public function __construct(private ?DnsResolver $resolver = null) {}

    /**
     * Validate each proposal against real DNS: does the final proposed FQDN
     * actually resolve, and does NetBox's recorded IP agree with where it
     * points? A name we don't propose a value for (a flag) has nothing to
     * resolve, so its resolution is left null.
     *
     * @param  array<int, ProposedChange>  $proposals
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, ValidatedChange>
     */
    public function validate(array $proposals, array $records): array
    {
        $validated = [];

        foreach ($proposals as $i => $change) {
            $record = $records[$i];

            if ($change->proposed === null) {
                $validated[] = new ValidatedChange(
                    $change,
                    resolves: null,
                    ipCheck: $this->checkIp($record, []),
                );

                continue;
            }

            $lookup = $this->resolver()->lookup($change->proposed);

            $validated[] = new ValidatedChange(
                $change,
                resolves: $lookup['resolved'],
                ipCheck: $this->checkIp($record, $lookup['ips']),
            );
        }

        return $validated;
    }

    /**
     * Cross-check NetBox's recorded primary IP against the addresses the name
     * actually resolves to. Most records carry no IP; IPv6 ones can't be
     * verified against the A-record lookups we do.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $resolvedIps
     */
    private function checkIp(array $record, array $resolvedIps): IpCheck
    {
        $address = data_get($record, 'primary_ip.address');

        if ($address === null) {
            return IpCheck::NoNetboxIp;
        }

        $ip = explode('/', (string) $address)[0];

        if (str_contains($ip, ':')) {
            return IpCheck::Ipv6Unverifiable;
        }

        if ($resolvedIps === []) {
            return IpCheck::Unverified;
        }

        return in_array($ip, $resolvedIps, true) ? IpCheck::Match : IpCheck::Mismatch;
    }

    private function resolver(): DnsResolver
    {
        return $this->resolver ??= DnsResolver::make();
    }
}
