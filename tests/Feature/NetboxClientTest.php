<?php

use App\Enums\OsType;
use App\Services\Netbox\NetboxClient;
use Illuminate\Support\Facades\Http;

it('maps netbox platform names to os types', function () {
    expect(NetboxClient::osTypeForPlatform('Windows Server 2019'))->toBe(OsType::Windows)
        ->and(NetboxClient::osTypeForPlatform('ubuntu-22-04'))->toBe(OsType::Linux)
        ->and(NetboxClient::osTypeForPlatform('Red Hat Enterprise Linux 9'))->toBe(OsType::Linux)
        ->and(NetboxClient::osTypeForPlatform('Proxmox 8'))->toBe(OsType::Linux)
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

    $servers = collect((new NetboxClient('https://netbox.test', 'key123', 'token123'))->activeServers());

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

it('returns raw untouched device and VM payloads, grouped and following pagination', function () {
    Http::fake([
        'netbox.test/api/dcim/devices/*' => Http::sequence()
            ->push(['results' => [['id' => 5, 'name' => 'dc1.example.com', 'description' => 'a host', 'platform' => ['name' => 'Ubuntu 22.04']]], 'next' => 'https://netbox.test/api/dcim/devices/?status=active&role=server&offset=1'])
            ->push(['results' => [['id' => 6, 'name' => 'dc2.example.com']], 'next' => null]),
        'netbox.test/api/virtualization/virtual-machines/*' => Http::response(['results' => [['id' => 9, 'name' => 'vm-01.example.com', 'comments' => 'notes']], 'next' => null]),
    ]);

    $raw = (new NetboxClient('https://netbox.test', 'key', 'token'))->rawActiveServers();

    expect($raw)->toHaveKeys(['devices', 'virtual_machines'])
        ->and($raw['devices'])->toHaveCount(2)
        ->and($raw['virtual_machines'])->toHaveCount(1)
        ->and($raw['devices'][0])->toBe(['id' => 5, 'name' => 'dc1.example.com', 'description' => 'a host', 'platform' => ['name' => 'Ubuntu 22.04']])
        ->and($raw['virtual_machines'][0])->toBe(['id' => 9, 'name' => 'vm-01.example.com', 'comments' => 'notes']);
});

it('authenticates with a Bearer key.token header', function () {
    Http::fake([
        'netbox.test/*' => Http::response(['results' => [], 'next' => null]),
    ]);

    (new NetboxClient('https://netbox.test', 'keyprefix', 'secrettoken'))->activeServers();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer keyprefix.secrettoken'));
});

it('asks NetBox only for servers, filtering devices and VMs by role=server', function () {
    Http::fake([
        'netbox.test/*' => Http::response(['results' => [], 'next' => null]),
    ]);

    (new NetboxClient('https://netbox.test', 'key', 'token'))->activeServers();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/dcim/devices/') && str_contains($request->url(), 'role=server'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/virtualization/virtual-machines/') && str_contains($request->url(), 'role=server'));
});

it('builds a client from config, trimming a trailing slash off the base url', function () {
    config([
        'patchmon.netbox.base_url' => 'https://netbox.example.test/',
        'patchmon.netbox.key' => 'secret-key',
        'patchmon.netbox.token' => 'secret-token',
    ]);

    $client = NetboxClient::make();

    expect($client->baseUrl)->toBe('https://netbox.example.test')
        ->and($client->key)->toBe('secret-key')
        ->and($client->token)->toBe('secret-token');
});
