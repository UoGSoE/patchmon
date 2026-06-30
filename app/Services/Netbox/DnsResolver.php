<?php

namespace App\Services\Netbox;

use App\Enums\HostnameResolutionStatus;
use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

class DnsResolver
{
    /**
     * @var array<string, array{resolved: bool, ips: array<int, string>, checked_at: string}>|null
     */
    private ?array $cache = null;

    /**
     * @param  Closure(string): array<int, array<string, mixed>>  $resolver
     */
    public function __construct(
        private Closure $resolver,
        private string $disk = 'netbox',
        private string $cacheFile = 'dns-cache.json',
        private int $delaySeconds = 0,
    ) {}

    public static function make(): self
    {
        return new self(
            resolver: fn (string $fqdn) => @dns_get_record($fqdn, DNS_A) ?: [],
            delaySeconds: 1,
        );
    }

    /**
     * Whether the given FQDN resolves to at least one A record.
     */
    public function resolves(string $fqdn): bool
    {
        return $this->lookup($fqdn)['resolved'];
    }

    /**
     * Resolve a bare hostname against candidate subdomains: build
     * host.subdomain.domain for each candidate and accept only a unique hit.
     * Zero or more than one hit is flagged for a human — we never guess the
     * department. Subdomains and domain default to the configured set.
     *
     * @param  array<int, string>|null  $subdomains
     */
    public function resolveBareHostname(string $host, ?array $subdomains = null, ?string $domain = null): HostnameResolution
    {
        $subdomains ??= config('patchmon.netbox.subdomains');
        $domain ??= config('patchmon.netbox.default_domain');

        $resolved = array_values(array_filter(
            array_map(fn (string $subdomain) => "{$host}.{$subdomain}.{$domain}", $subdomains),
            fn (string $fqdn) => $this->resolves($fqdn),
        ));

        $status = match (count($resolved)) {
            1 => HostnameResolutionStatus::Accepted,
            0 => HostnameResolutionStatus::NoMatch,
            default => HostnameResolutionStatus::Ambiguous,
        };

        return new HostnameResolution(
            status: $status,
            fqdn: $status === HostnameResolutionStatus::Accepted ? $resolved[0] : null,
            resolved: $resolved,
        );
    }

    /**
     * Resolve a single FQDN, consulting and populating the on-disk cache. Both
     * hits and misses are recorded so the picture builds up over successive
     * on-network runs without re-hammering DNS for names already seen.
     *
     * @return array{resolved: bool, ips: array<int, string>, checked_at: string}
     */
    public function lookup(string $fqdn): array
    {
        $cache = $this->cache();

        if (isset($cache[$fqdn])) {
            return $cache[$fqdn];
        }

        $records = ($this->resolver)($fqdn);

        // A real query went out — pause before the next one so a full sweep
        // doesn't burst hundreds of lookups at DNS. Cached lookups never reach
        // here, so re-runs over a warm cache stay instant.
        if ($this->delaySeconds > 0) {
            Sleep::for($this->delaySeconds)->seconds();
        }

        $entry = [
            'resolved' => $records !== [],
            'ips' => array_values(array_filter(array_column($records, 'ip'))),
            'checked_at' => now()->toIso8601String(),
        ];

        $this->cache[$fqdn] = $entry;
        $this->persist();

        return $entry;
    }

    /**
     * The cache, loaded from disk once per instance.
     *
     * @return array<string, array{resolved: bool, ips: array<int, string>, checked_at: string}>
     */
    private function cache(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $disk = Storage::disk($this->disk);

        return $this->cache = $disk->exists($this->cacheFile)
            ? json_decode($disk->get($this->cacheFile), true) ?? []
            : [];
    }

    private function persist(): void
    {
        Storage::disk($this->disk)->put(
            $this->cacheFile,
            json_encode($this->cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}
