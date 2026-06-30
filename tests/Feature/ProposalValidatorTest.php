<?php

use App\Enums\ChangeStatus;
use App\Enums\FlagReason;
use App\Enums\IpCheck;
use App\Services\Netbox\DnsResolver;
use App\Services\Netbox\ProposalValidator;
use App\Services\Netbox\ProposedChange;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('netbox');
});

it('annotates whether a proposed fqdn resolves', function () {
    $resolver = new DnsResolver(
        resolver: fn (string $fqdn) => $fqdn === 'server1.dept-a.example.ac.uk' ? [['ip' => '10.0.0.1']] : [],
    );

    $proposal = new ProposedChange(
        original: 'server1.dept-a',
        proposed: 'server1.dept-a.example.ac.uk',
        status: ChangeStatus::Propose,
    );

    $validated = (new ProposalValidator($resolver))->validate([$proposal], [['name' => 'server1.dept-a']]);

    expect($validated)->toHaveCount(1)
        ->and($validated[0]->change)->toBe($proposal)
        ->and($validated[0]->resolves)->toBeTrue()
        ->and($validated[0]->ipCheck)->toBe(IpCheck::NoNetboxIp);
});

it('flags a proposed fqdn that does not resolve as a possible typo', function () {
    $resolver = new DnsResolver(resolver: fn (string $fqdn) => []);

    $proposal = new ProposedChange(
        original: 'servr1.dept-a',
        proposed: 'servr1.dept-a.example.ac.uk',
        status: ChangeStatus::Propose,
    );

    $validated = (new ProposalValidator($resolver))->validate([$proposal], [['name' => 'servr1.dept-a']]);

    expect($validated[0]->resolves)->toBeFalse();
});

it('leaves a flagged record with no proposed name unchecked', function () {
    $queried = [];
    $resolver = new DnsResolver(
        resolver: function (string $fqdn) use (&$queried) {
            $queried[] = $fqdn;

            return [];
        },
    );

    $proposal = new ProposedChange(
        original: 'Cab 1',
        proposed: null,
        status: ChangeStatus::Flag,
        reason: FlagReason::UnclearName,
    );

    $validated = (new ProposalValidator($resolver))->validate([$proposal], [['name' => 'Cab 1']]);

    expect($validated[0]->resolves)->toBeNull()
        ->and($queried)->toBe([]);
});

it('flags an ip mismatch when netbox primary_ip disagrees with dns', function () {
    $resolver = new DnsResolver(
        resolver: fn (string $fqdn) => $fqdn === 'server8.dept-d.example.ac.uk' ? [['ip' => '203.0.113.171']] : [],
    );

    $proposal = new ProposedChange(
        original: 'server8.dept-d',
        proposed: 'server8.dept-d.example.ac.uk',
        status: ChangeStatus::Propose,
    );

    $record = [
        'name' => 'server8.dept-d',
        'primary_ip' => ['address' => '10.99.99.99/24'],
    ];

    $validated = (new ProposalValidator($resolver))->validate([$proposal], [$record]);

    expect($validated[0]->ipCheck)->toBe(IpCheck::Mismatch);
});

it('confirms an ip match when netbox primary_ip agrees with dns', function () {
    $resolver = new DnsResolver(
        resolver: fn (string $fqdn) => $fqdn === 'server8.dept-d.example.ac.uk' ? [['ip' => '203.0.113.171']] : [],
    );

    $proposal = new ProposedChange(
        original: 'server8.dept-d',
        proposed: 'server8.dept-d.example.ac.uk',
        status: ChangeStatus::Propose,
    );

    $record = [
        'name' => 'server8.dept-d',
        'primary_ip' => ['address' => '203.0.113.171/24'],
    ];

    $validated = (new ProposalValidator($resolver))->validate([$proposal], [$record]);

    expect($validated[0]->ipCheck)->toBe(IpCheck::Match);
});

it('marks an ipv6 primary_ip as unverifiable against our a-record lookups', function () {
    $resolver = new DnsResolver(
        resolver: fn (string $fqdn) => [['ip' => '172.20.0.1']],
    );

    $proposal = new ProposedChange(
        original: 'server9.dept-d',
        proposed: 'server9.dept-d.example.ac.uk',
        status: ChangeStatus::Propose,
    );

    $record = [
        'name' => 'server9.dept-d',
        'primary_ip' => ['address' => '2001:db8::1/64'],
    ];

    $validated = (new ProposalValidator($resolver))->validate([$proposal], [$record]);

    expect($validated[0]->ipCheck)->toBe(IpCheck::Ipv6Unverifiable);
});

it('does not call a non-resolving name an ip mismatch — it cannot be verified', function () {
    $resolver = new DnsResolver(resolver: fn (string $fqdn) => []);

    $proposal = new ProposedChange(
        original: 'login1.cluster-x',
        proposed: 'login1.dept-j.example.ac.uk',
        status: ChangeStatus::Propose,
    );

    $record = [
        'name' => 'login1.cluster-x',
        'primary_ip' => ['address' => '203.0.113.228/24'],
    ];

    $validated = (new ProposalValidator($resolver))->validate([$proposal], [$record]);

    expect($validated[0]->resolves)->toBeFalse()
        ->and($validated[0]->ipCheck)->toBe(IpCheck::Unverified);
});
