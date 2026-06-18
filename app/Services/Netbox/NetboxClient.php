<?php

namespace App\Services\Netbox;

use App\Enums\OsType;
use Illuminate\Support\Facades\Http;

class NetboxClient
{
    public function __construct(
        public string $baseUrl,
        public string $token,
        public bool $verifyTls = true,
        public int $timeout = 10,
    ) {}

    public static function make(): self
    {
        return new self(
            rtrim((string) config('patchmon.netbox.base_url'), '/'),
            (string) config('patchmon.netbox.token'),
            (bool) config('patchmon.netbox.verify_tls', true),
            (int) config('patchmon.netbox.timeout', 10),
        );
    }

    /**
     * Every active server in NetBox, both physical devices and virtual machines,
     * normalised into our own shape.
     *
     * @return array<int, NetboxServer>
     */
    public function activeServers(): array
    {
        return [
            ...$this->fetch($this->baseUrl.'/api/dcim/devices/?status=active', isVirtual: false),
            ...$this->fetch($this->baseUrl.'/api/virtualization/virtual-machines/?status=active', isVirtual: true),
        ];
    }

    /**
     * Walk a paginated NetBox list endpoint, following `next` until it runs out.
     *
     * @return array<int, NetboxServer>
     */
    private function fetch(string $url, bool $isVirtual): array
    {
        $servers = [];

        while ($url !== null) {
            $body = Http::withHeaders(['Authorization' => 'Token '.$this->token])
                ->withOptions(['verify' => $this->verifyTls])
                ->timeout($this->timeout)
                ->acceptJson()
                ->get($url)
                ->throw()
                ->json();

            foreach ($body['results'] ?? [] as $result) {
                $servers[] = new NetboxServer(
                    netboxId: (int) $result['id'],
                    isVirtual: $isVirtual,
                    name: (string) $result['name'],
                    osType: self::osTypeForPlatform(data_get($result, 'platform.name') ?? data_get($result, 'platform.slug')),
                );
            }

            $url = $body['next'] ?? null;
        }

        return $servers;
    }

    /**
     * Best-effort mapping of a NetBox platform name/slug to one of our OS types.
     * NetBox platform data is free-form and often sparse, so anything we don't
     * recognise falls back to Other for a human to correct during triage.
     */
    public static function osTypeForPlatform(?string $platform): OsType
    {
        $platform = strtolower(trim((string) $platform));

        if ($platform === '') {
            return OsType::Other;
        }

        if (str_contains($platform, 'win')) {
            return OsType::Windows;
        }

        $linuxMarkers = ['linux', 'ubuntu', 'debian', 'centos', 'rhel', 'red hat', 'redhat', 'rocky', 'alma', 'suse', 'sles', 'fedora'];

        foreach ($linuxMarkers as $marker) {
            if (str_contains($platform, $marker)) {
                return OsType::Linux;
            }
        }

        return OsType::Other;
    }
}
