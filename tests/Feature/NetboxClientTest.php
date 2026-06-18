<?php

use App\Enums\OsType;
use App\Services\Netbox\NetboxClient;
use Illuminate\Support\Facades\Http;

it('maps netbox platform names to os types', function () {
    expect(NetboxClient::osTypeForPlatform('Windows Server 2019'))->toBe(OsType::Windows)
        ->and(NetboxClient::osTypeForPlatform('ubuntu-22-04'))->toBe(OsType::Linux)
        ->and(NetboxClient::osTypeForPlatform('Red Hat Enterprise Linux 9'))->toBe(OsType::Linux)
        ->and(NetboxClient::osTypeForPlatform('Cisco IOS'))->toBe(OsType::Other)
        ->and(NetboxClient::osTypeForPlatform(null))->toBe(OsType::Other);
});

it('fetches active devices and virtual machines across pages and normalises them', function () {
    $devicesPage1 = [
        'results' => [
            ['id' => 5, 'name' => 'dc1.example.com', 'platform' => ['name' => 'Ubuntu 22.04']],
        ],
        'next' => 'https://netbox.test/api/dcim/devices/?status=active&offset=1',
    ];
    $devicesPage2 = [
        'results' => [
            ['id' => 6, 'name' => 'win-01.example.com', 'platform' => ['name' => 'Windows Server 2022']],
        ],
        'next' => null,
    ];
    $vms = [
        'results' => [
            ['id' => 5, 'name' => 'vm-app-01.example.com', 'platform' => ['name' => 'Debian 12']],
            ['id' => 9, 'name' => 'vm-misc-01.example.com', 'platform' => null],
        ],
        'next' => null,
    ];

    Http::fake([
        'netbox.test/api/dcim/devices/*' => Http::sequence()
            ->push($devicesPage1)
            ->push($devicesPage2),
        'netbox.test/api/virtualization/virtual-machines/*' => Http::response($vms),
    ]);

    $servers = collect((new NetboxClient('https://netbox.test', 'token123'))->activeServers());

    $device5 = $servers->firstWhere(fn ($s) => ! $s->isVirtual && $s->netboxId === 5);
    $vm5 = $servers->firstWhere(fn ($s) => $s->isVirtual && $s->netboxId === 5);

    expect($servers)->toHaveCount(4)
        ->and($device5->name)->toBe('dc1.example.com')
        ->and($device5->osType)->toBe(OsType::Linux)
        ->and($vm5->name)->toBe('vm-app-01.example.com')
        ->and($vm5->osType)->toBe(OsType::Linux)
        ->and($servers->firstWhere(fn ($s) => $s->netboxId === 6)->osType)->toBe(OsType::Windows)
        ->and($servers->firstWhere(fn ($s) => $s->isVirtual && $s->netboxId === 9)->osType)->toBe(OsType::Other);
});

// REMINDER — our pagination handling (NetboxClient::fetch follows `next` until null)
// is written against assumed NetBox v4 shapes, with no real responses to check it
// against. Once we have an API key, capture real fixtures and confirm the awkward
// cases: `next` is an absolute URL we can follow as-is (does it point at the same
// host as base_url, or an internal one we can't reach?); offset/limit defaults don't
// silently cap a ~1000-server estate at one page; and a server added/removed mid-walk
// isn't dropped or double-counted. Then replace this with real assertions.
it('handles real-world netbox pagination quirks', function () {
    expect(true)->toBeTrue();
})->skip('Pending NetBox API access — verify pagination against captured real responses (assumed v4).');

it('builds a client from config, trimming a trailing slash off the base url', function () {
    config([
        'patchmon.netbox.base_url' => 'https://netbox.example.test/',
        'patchmon.netbox.token' => 'secret-token',
    ]);

    $client = NetboxClient::make();

    expect($client->baseUrl)->toBe('https://netbox.example.test')
        ->and($client->token)->toBe('secret-token');
});
