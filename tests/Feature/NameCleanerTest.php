<?php

use App\Enums\ChangeStatus;
use App\Enums\FlagReason;
use App\Services\Netbox\DnsResolver;
use App\Services\Netbox\NameCleaner;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('netbox');

    config([
        'patchmon.netbox.default_domain' => 'example.ac.uk',
        'patchmon.netbox.subdomains' => ['dept-a', 'dept-b', 'dept-c', 'dept-d', 'dept-e', 'dept-f', 'dept-g', 'dept-h', 'dept-i', 'dept-j'],
        'patchmon.netbox.building_departments' => [
            'Building A' => ['dept-a', 'dept-b', 'dept-c'],
            'Building B' => ['dept-a', 'dept-b', 'dept-c'],
            'Building C' => ['dept-g'],
            'Building D' => ['dept-g'],
            'Building E' => ['dept-h'],
            'Building F' => ['dept-f'],
        ],
        'patchmon.netbox.department_aliases' => ['cluster-x' => 'dept-j'],
    ]);
});

it('proposes appending the default domain to a host.dept name', function () {
    $change = (new NameCleaner)->propose(['name' => 'server1.dept-a']);

    expect($change->original)->toBe('server1.dept-a')
        ->and($change->proposed)->toBe('server1.dept-a.example.ac.uk')
        ->and($change->status)->toBe(ChangeStatus::Propose);
});

it('lowercases the name as part of the proposed change', function () {
    $change = (new NameCleaner)->propose(['name' => 'Server2.dept-d']);

    expect($change->original)->toBe('Server2.dept-d')
        ->and($change->proposed)->toBe('server2.dept-d.example.ac.uk')
        ->and($change->status)->toBe(ChangeStatus::Propose);
});

it('rewrites a known department alias to its canonical subdomain', function () {
    $change = (new NameCleaner)->propose(['name' => 'login1.cluster-x']);

    expect($change->status)->toBe(ChangeStatus::Propose)
        ->and($change->original)->toBe('login1.cluster-x')
        ->and($change->proposed)->toBe('login1.dept-j.example.ac.uk');
});

it('treats dept-e as a sub-domain in its own right, not an alias for dept-d', function () {
    $change = (new NameCleaner)->propose(['name' => 'server3.dept-e']);

    expect($change->status)->toBe(ChangeStatus::Propose)
        ->and($change->proposed)->toBe('server3.dept-e.example.ac.uk');
});

it('flags placeholder names for a human instead of guessing', function () {
    $change = (new NameCleaner)->propose(['name' => 'Unlabeled R3 5']);

    expect($change->status)->toBe(ChangeStatus::Flag)
        ->and($change->proposed)->toBeNull()
        ->and($change->reason)->toBe(FlagReason::Placeholder);
});

it('strips a parenthetical annotation into comments and cleans the remaining name', function () {
    $change = (new NameCleaner)->propose([
        'name' => 'server4.dept-g (WebServer_Ubuntu_22.04)',
        'comments' => 'Existing note.',
    ]);

    expect($change->status)->toBe(ChangeStatus::Propose)
        ->and($change->original)->toBe('server4.dept-g (WebServer_Ubuntu_22.04)')
        ->and($change->proposed)->toBe('server4.dept-g.example.ac.uk')
        ->and($change->proposedComments)->toBe('Existing note. WebServer_Ubuntu_22.04');
});

it('uses the stripped annotation alone as comments when there are none to append to', function () {
    $change = (new NameCleaner)->propose(['name' => 'server5.dept-g (appserver-ubuntu22.04)']);

    expect($change->proposed)->toBe('server5.dept-g.example.ac.uk')
        ->and($change->proposedComments)->toBe('appserver-ubuntu22.04');
});

it('resolves a bare hostname in a trusted single-dept building and proposes the fqdn', function () {
    $resolver = new DnsResolver(
        resolver: fn (string $fqdn) => $fqdn === 'server6.dept-a.example.ac.uk' ? [['ip' => '10.0.0.1']] : [],
    );

    $change = (new NameCleaner($resolver))->propose([
        'name' => 'server6',
        'site' => ['name' => 'Building A'],
    ]);

    expect($change->status)->toBe(ChangeStatus::Propose)
        ->and($change->proposed)->toBe('server6.dept-a.example.ac.uk');
});

it('narrows the candidate subdomains to the building department, cutting dns lookups', function () {
    $queried = [];
    $resolver = new DnsResolver(
        resolver: function (string $fqdn) use (&$queried) {
            $queried[] = $fqdn;

            return $fqdn === 'node001.dept-a.example.ac.uk' ? [['ip' => '10.0.0.1']] : [];
        },
    );

    (new NameCleaner($resolver))->propose([
        'name' => 'node001',
        'site' => ['name' => 'Building A'],
    ]);

    expect($queried)->toBe([
        'node001.dept-a.example.ac.uk',
        'node001.dept-b.example.ac.uk',
        'node001.dept-c.example.ac.uk',
    ]);
});

it('falls back to the full subdomain set for a host with no usable building signal', function () {
    $resolver = new DnsResolver(
        resolver: fn (string $fqdn) => $fqdn === 'server7.dept-d.example.ac.uk' ? [['ip' => '10.0.0.1']] : [],
    );

    $change = (new NameCleaner($resolver))->propose([
        'name' => 'server7',
        'site' => null,
    ]);

    expect($change->status)->toBe(ChangeStatus::Propose)
        ->and($change->proposed)->toBe('server7.dept-d.example.ac.uk');
});

it('flags a bare hostname that resolves under no department', function () {
    $resolver = new DnsResolver(resolver: fn (string $fqdn) => []);

    $change = (new NameCleaner($resolver))->propose([
        'name' => 'node002',
        'site' => ['name' => 'Building A'],
    ]);

    expect($change->status)->toBe(ChangeStatus::Flag)
        ->and($change->proposed)->toBeNull()
        ->and($change->reason)->toBe(FlagReason::UnresolvedHostname);
});

it('flags a bare hostname that resolves under more than one department', function () {
    $resolver = new DnsResolver(
        resolver: fn (string $fqdn) => in_array($fqdn, ['server8.dept-d.example.ac.uk', 'server8.dept-f.example.ac.uk'], true) ? [['ip' => '10.0.0.1']] : [],
    );

    $change = (new NameCleaner($resolver))->propose([
        'name' => 'server8',
        'site' => null,
    ]);

    expect($change->status)->toBe(ChangeStatus::Flag)
        ->and($change->proposed)->toBeNull()
        ->and($change->reason)->toBe(FlagReason::AmbiguousHostname);
});

it('flags a single-dot name whose department token is not a known subdomain', function () {
    $change = (new NameCleaner)->propose(['name' => 'dc4.unknown-dept']);

    expect($change->status)->toBe(ChangeStatus::Flag)
        ->and($change->proposed)->toBeNull()
        ->and($change->reason)->toBe(FlagReason::UnknownDepartment);
});

it('flags a name that is not a recognisable hostname for manual cleanup', function () {
    $change = (new NameCleaner)->propose(['name' => 'Cab 1']);

    expect($change->status)->toBe(ChangeStatus::Flag)
        ->and($change->proposed)->toBeNull()
        ->and($change->reason)->toBe(FlagReason::UnclearName);
});

it('flags an underscore-bearing multi-segment name for manual cleanup', function () {
    $change = (new NameCleaner)->propose(['name' => 'disk_shelf.server9.dept-f']);

    expect($change->status)->toBe(ChangeStatus::Flag)
        ->and($change->reason)->toBe(FlagReason::UnclearName);
});

it('flags a host.dept name with a space in the host label as unclear, not a proposal', function () {
    $change = (new NameCleaner)->propose(['name' => 'new server10.dept-a']);

    expect($change->status)->toBe(ChangeStatus::Flag)
        ->and($change->proposed)->toBeNull()
        ->and($change->reason)->toBe(FlagReason::UnclearName);
});

it('leaves an already-valid multi-label fqdn unchanged', function () {
    $change = (new NameCleaner)->propose(['name' => 'server11.dept-d.example.ac.uk']);

    expect($change->status)->toBe(ChangeStatus::Unchanged)
        ->and($change->proposed)->toBe('server11.dept-d.example.ac.uk');
});

it('proposes lowercasing an otherwise-valid fqdn that carries uppercase', function () {
    $change = (new NameCleaner)->propose(['name' => 'MixedCaseHost.dept-d.example.ac.uk']);

    expect($change->status)->toBe(ChangeStatus::Propose)
        ->and($change->proposed)->toBe('mixedcasehost.dept-d.example.ac.uk');
});

it('flags names that collide across the record set, leaving unique names proposed', function () {
    $proposals = (new NameCleaner)->proposals([
        ['name' => 'server12.dept-d'],
        ['name' => 'server12.dept-d'],
        ['name' => 'server1.dept-a'],
    ]);

    expect($proposals)->toHaveCount(3)
        ->and($proposals[0]->status)->toBe(ChangeStatus::Flag)
        ->and($proposals[0]->reason)->toBe(FlagReason::NameCollision)
        ->and($proposals[1]->reason)->toBe(FlagReason::NameCollision)
        ->and($proposals[2]->status)->toBe(ChangeStatus::Propose)
        ->and($proposals[2]->proposed)->toBe('server1.dept-a.example.ac.uk');
});
