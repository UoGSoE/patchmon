<?php

use App\Services\Netbox\DnsResolver;
use App\Services\Netbox\NameCleaner;
use App\Services\Netbox\ProposalReport;
use App\Services\Netbox\ProposalValidator;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('netbox');

    config([
        'patchmon.netbox.default_domain' => 'example.ac.uk',
        'patchmon.netbox.subdomains' => ['dept-a', 'dept-d', 'dept-j'],
        'patchmon.netbox.department_aliases' => ['cluster-x' => 'dept-j'],
    ]);
});

/**
 * Run a set of raw records through the engine + validator with a fake resolver,
 * then render the report — the realistic chain, with validated/records aligned.
 */
function reportFor(array $records, callable $resolver): string
{
    $dns = new DnsResolver(resolver: $resolver);
    $proposals = (new NameCleaner($dns))->proposals($records);
    $validated = (new ProposalValidator($dns))->validate($proposals, $records);

    return (new ProposalReport)->render($validated, $records);
}

it('renders a summary of the outcome counts', function () {
    $report = reportFor([
        ['id' => 1, 'url' => 'https://netbox.test/api/dcim/devices/1/', 'name' => 'server1.dept-a'],
        ['id' => 2, 'url' => 'https://netbox.test/api/dcim/devices/2/', 'name' => 'Cab 1'],
        ['id' => 3, 'url' => 'https://netbox.test/api/virtualization/virtual-machines/3/', 'name' => 'login1.cluster-x'],
    ], fn (string $fqdn) => $fqdn === 'server1.dept-a.example.ac.uk' ? [['ip' => '10.0.0.1']] : []);

    expect($report)->toContain('# NetBox proposed changes')
        ->toContain('Records reviewed | 3')
        ->toContain('Ready to apply | 1')
        ->toContain('Proposed but unverified | 1')
        ->toContain('Flagged for manual cleanup | 1');
});

it('explains that bracketed detail is moved to comments, not dropped', function () {
    $report = reportFor([
        ['id' => 1, 'url' => 'https://netbox.test/api/dcim/devices/1/', 'name' => 'server1.dept-a'],
    ], fn (string $fqdn) => []);

    expect($report)->toContain('NetBox comments field rather than dropped');
});

it('summarises the ip cross-check results', function () {
    $report = reportFor([
        ['id' => 1, 'url' => 'https://netbox.test/api/dcim/devices/1/', 'name' => 'server1.dept-a', 'primary_ip' => ['address' => '10.0.0.1/24']],
    ], fn (string $fqdn) => $fqdn === 'server1.dept-a.example.ac.uk' ? [['ip' => '10.0.0.1']] : []);

    expect($report)->toContain('IP cross-check: 1 match, 0 mismatch, 0 unverified');
});

it('lists resolving proposals as old to new under ready to apply', function () {
    $report = reportFor([
        ['id' => 1, 'url' => 'https://netbox.test/api/dcim/devices/1/', 'name' => 'server1.dept-a'],
    ], fn (string $fqdn) => $fqdn === 'server1.dept-a.example.ac.uk' ? [['ip' => '10.0.0.1']] : []);

    expect($report)->toContain('## Ready to apply')
        ->toContain('`server1.dept-a` → `server1.dept-a.example.ac.uk`');
});

it('hides unclear (possibly personal) names behind a netbox link but shows other flags by name', function () {
    $report = reportFor([
        ['id' => 5, 'url' => 'https://netbox.test/api/dcim/devices/5/', 'name' => 'Jane Doe'],
        ['id' => 6, 'url' => 'https://netbox.test/api/dcim/devices/6/', 'name' => 'dc4.unknown-dept'],
    ], fn (string $fqdn) => []);

    expect($report)->toContain('## Flagged for manual cleanup')
        ->toContain('`dc4.unknown-dept`')
        ->not->toContain('Jane Doe')
        ->toContain('NetBox #5')
        ->toContain('https://netbox.test/dcim/devices/5/');
});

it('splits unverified proposals into expected aliases and ones to investigate', function () {
    $report = reportFor([
        ['id' => 7, 'url' => 'https://netbox.test/api/virtualization/virtual-machines/7/', 'name' => 'node01.cluster-x'],
        ['id' => 8, 'url' => 'https://netbox.test/api/dcim/devices/8/', 'name' => 'servr1.dept-a'],
    ], fn (string $fqdn) => []);

    expect($report)->toContain('## Proposed but unverified')
        ->toContain('### Expected — aliased department, host not in campus DNS (1)')
        ->toContain('`node01.cluster-x` → `node01.dept-j.example.ac.uk`')
        ->toContain('### Investigate — possible typo (1)')
        ->toContain('`servr1.dept-a` → `servr1.dept-a.example.ac.uk`');
});

it('lists ip mismatches with the netbox address for verification', function () {
    $report = reportFor([
        ['id' => 9, 'url' => 'https://netbox.test/api/dcim/devices/9/', 'name' => 'server8.dept-d', 'primary_ip' => ['address' => '10.99.99.99/24']],
    ], fn (string $fqdn) => $fqdn === 'server8.dept-d.example.ac.uk' ? [['ip' => '172.20.0.5']] : []);

    expect($report)->toContain('## IP mismatches')
        ->toContain('`server8.dept-d.example.ac.uk`')
        ->toContain('10.99.99.99')
        ->toContain('https://netbox.test/dcim/devices/9/');
});
