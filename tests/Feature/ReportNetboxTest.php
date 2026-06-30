<?php

use App\Services\Netbox\DnsResolver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('netbox');

    config([
        'patchmon.netbox.default_domain' => 'example.ac.uk',
        'patchmon.netbox.subdomains' => ['dept-a', 'dept-d', 'dept-j'],
        'patchmon.netbox.department_aliases' => ['cluster-x' => 'dept-j'],
    ]);

    $this->app->instance(DnsResolver::class, new DnsResolver(
        resolver: fn (string $fqdn) => $fqdn === 'server1.dept-a.example.ac.uk' ? [['ip' => '10.0.0.1']] : [],
    ));
});

it('writes a markdown report from the fixture', function () {
    Storage::disk('netbox')->put('servers.json', json_encode([
        'devices' => [
            ['id' => 1, 'url' => 'https://netbox.test/api/dcim/devices/1/', 'name' => 'server1.dept-a'],
        ],
        'virtual_machines' => [],
    ]));

    $exitCode = Artisan::call('netbox:report');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('1 NetBox records: 1 ready');

    Storage::disk('netbox')->assertExists('proposed-changes.md');

    expect(Storage::disk('netbox')->get('proposed-changes.md'))
        ->toContain('# NetBox proposed changes')
        ->toContain('`server1.dept-a` → `server1.dept-a.example.ac.uk`');
});

it('fails clearly when no fixture has been fetched yet', function () {
    $exitCode = Artisan::call('netbox:report');

    expect($exitCode)->toBe(1)
        ->and(Storage::disk('netbox')->exists('proposed-changes.md'))->toBeFalse();
});
