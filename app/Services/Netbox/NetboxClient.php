<?php

namespace App\Services\Netbox;

use App\Enums\OsType;
use Illuminate\Support\Facades\Http;

class NetboxClient
{
    public function __construct(
        public string $baseUrl,
        public string $key,
        public string $token,
        public bool $verifyTls = true,
        public int $timeout = 10,
    ) {}

    public static function make(): self
    {
        return new self(
            rtrim((string) config('patchmon.netbox.base_url'), '/'),
            (string) config('patchmon.netbox.key'),
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
            ...$this->fetch($this->baseUrl.'/api/dcim/devices/?status=active&role=server', isVirtual: false),
            ...$this->fetch($this->baseUrl.'/api/virtualization/virtual-machines/?status=active&role=server', isVirtual: true),
        ];
    }

    /**
     * Raw, untouched device + VM payloads for every active server, grouped by
     * which NetBox list each came from. The cleanup pass needs fields the
     * normalised activeServers() discards — display, platform, description,
     * comments, primary_ip and any custom fields.
     *
     * @return array{devices: array<int, array<string, mixed>>, virtual_machines: array<int, array<string, mixed>>}
     */
    public function rawActiveServers(): array
    {
        return [
            'devices' => $this->fetchRaw($this->baseUrl.'/api/dcim/devices/?status=active&role=server'),
            'virtual_machines' => $this->fetchRaw($this->baseUrl.'/api/virtualization/virtual-machines/?status=active&role=server'),
        ];
    }

    /**
     * Fetch a NetBox list endpoint and normalise each record into our own shape.
     *
     * @return array<int, NetboxServer>
     */
    private function fetch(string $url, bool $isVirtual): array
    {
        return array_map(
            fn (array $result) => new NetboxServer(
                netboxId: (int) $result['id'],
                isVirtual: $isVirtual,
                name: (string) $result['name'],
                osType: self::osTypeForPlatform(data_get($result, 'platform.name') ?? data_get($result, 'platform.slug')),
            ),
            $this->fetchRaw($url),
        );
    }

    /**
     * Walk a paginated NetBox list endpoint, following `next` until it runs out,
     * returning the untouched result records.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchRaw(string $url): array
    {
        $results = [];

        while ($url !== null) {
            $body = Http::withHeaders(['Authorization' => 'Bearer '.$this->key.'.'.$this->token])
                ->withOptions(['verify' => $this->verifyTls])
                ->timeout($this->timeout)
                ->acceptJson()
                ->get($url)
                ->throw()
                ->json();

            $results = [...$results, ...($body['results'] ?? [])];

            $url = $body['next'] ?? null;
        }

        return $results;
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
            return OsType::NetboxUnknown;
        }

        if (str_contains($platform, 'win')) {
            return OsType::Windows;
        }

        if (str_contains($platform, 'truenas')) {
            // SCALE (year-based version, 22+) is Debian-based Linux; CORE (13
            // and earlier) is FreeBSD and stays Other. A bare "truenas" can't
            // tell them apart, so read the version.
            return preg_match('/(\d+)/', $platform, $matches) === 1 && (int) $matches[1] >= 22
                ? OsType::Linux
                : OsType::Other;
        }

        $linuxMarkers = ['linux', 'ubuntu', 'debian', 'centos', 'rhel', 'red hat', 'redhat', 'rocky', 'alma', 'suse', 'sles', 'fedora', 'proxmox'];

        foreach ($linuxMarkers as $marker) {
            if (str_contains($platform, $marker)) {
                return OsType::Linux;
            }
        }

        return OsType::Other;
    }
}
