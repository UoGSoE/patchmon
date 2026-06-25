<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('writes the raw netbox set to the gitignored fixture with counts', function () {
    Storage::fake('netbox');

    config([
        'patchmon.netbox.base_url' => 'https://netbox.test',
        'patchmon.netbox.key' => 'key',
        'patchmon.netbox.token' => 'token',
    ]);

    Http::fake([
        'netbox.test/api/dcim/devices/*' => Http::response([
            'results' => [
                ['id' => 5, 'name' => 'dc1.example.com', 'platform' => ['name' => 'Ubuntu 22.04'], 'description' => 'a host'],
            ],
            'next' => null,
        ]),
        'netbox.test/api/virtualization/virtual-machines/*' => Http::response([
            'results' => [
                ['id' => 9, 'name' => 'vm-01.example.com'],
                ['id' => 10, 'name' => 'vm-02.example.com'],
            ],
            'next' => null,
        ]),
    ]);

    $this->artisan('netbox:fetch')
        ->expectsOutputToContain('Fetched 1 devices and 2 VMs')
        ->assertSuccessful();

    Storage::disk('netbox')->assertExists('servers.json');

    $fixture = json_decode(Storage::disk('netbox')->get('servers.json'), true);

    expect($fixture['counts'])->toBe(['devices' => 1, 'virtual_machines' => 2])
        ->and($fixture['devices'])->toHaveCount(1)
        ->and($fixture['virtual_machines'])->toHaveCount(2)
        ->and($fixture['devices'][0])->toBe(['id' => 5, 'name' => 'dc1.example.com', 'platform' => ['name' => 'Ubuntu 22.04'], 'description' => 'a host'])
        ->and($fixture)->toHaveKey('fetched_at');
});
