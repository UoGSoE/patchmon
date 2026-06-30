<?php

use App\Enums\HostnameResolutionStatus;
use App\Services\Netbox\DnsResolver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Storage::fake('netbox');
});

it('reports whether an fqdn resolves, using the injected resolver', function () {
    $resolver = new DnsResolver(
        resolver: fn (string $fqdn) => $fqdn === 'server1.dept-a.example.ac.uk' ? [['ip' => '10.0.0.1']] : [],
    );

    expect($resolver->resolves('server1.dept-a.example.ac.uk'))->toBeTrue()
        ->and($resolver->resolves('server1.dept-h.example.ac.uk'))->toBeFalse();
});

it('caches resolution results so repeated lookups skip dns, recording hits and misses', function () {
    $calls = [];

    $resolver = new DnsResolver(
        resolver: function (string $fqdn) use (&$calls) {
            $calls[$fqdn] = ($calls[$fqdn] ?? 0) + 1;

            return $fqdn === 'server1.dept-a.example.ac.uk' ? [['ip' => '10.0.0.1']] : [];
        },
    );

    $resolver->resolves('server1.dept-a.example.ac.uk');
    $resolver->resolves('server1.dept-a.example.ac.uk');
    $resolver->resolves('server2.dept-a.example.ac.uk');
    $resolver->resolves('server2.dept-a.example.ac.uk');

    expect($calls['server1.dept-a.example.ac.uk'])->toBe(1)
        ->and($calls['server2.dept-a.example.ac.uk'])->toBe(1);

    Storage::disk('netbox')->assertExists('dns-cache.json');

    $cache = json_decode(Storage::disk('netbox')->get('dns-cache.json'), true);

    expect($cache['server1.dept-a.example.ac.uk']['resolved'])->toBeTrue()
        ->and($cache['server1.dept-a.example.ac.uk']['ips'])->toBe(['10.0.0.1'])
        ->and($cache['server2.dept-a.example.ac.uk']['resolved'])->toBeFalse()
        ->and($cache['server2.dept-a.example.ac.uk']['ips'])->toBe([]);
});

it('accepts a bare hostname that resolves under exactly one candidate subdomain', function () {
    $resolver = new DnsResolver(
        resolver: fn (string $fqdn) => $fqdn === 'server1.dept-a.example.ac.uk' ? [['ip' => '10.0.0.1']] : [],
    );

    $result = $resolver->resolveBareHostname('server1', ['dept-a', 'dept-h', 'dept-g'], 'example.ac.uk');

    expect($result->status)->toBe(HostnameResolutionStatus::Accepted)
        ->and($result->fqdn)->toBe('server1.dept-a.example.ac.uk')
        ->and($result->resolved)->toBe(['server1.dept-a.example.ac.uk']);
});

it('flags a bare hostname that resolves under no candidate subdomain', function () {
    $resolver = new DnsResolver(resolver: fn (string $fqdn) => []);

    $result = $resolver->resolveBareHostname('server2', ['dept-a', 'dept-h'], 'example.ac.uk');

    expect($result->status)->toBe(HostnameResolutionStatus::NoMatch)
        ->and($result->fqdn)->toBeNull()
        ->and($result->resolved)->toBe([]);
});

it('flags a bare hostname that resolves under more than one candidate subdomain', function () {
    $resolver = new DnsResolver(
        resolver: fn (string $fqdn) => in_array($fqdn, ['server3.dept-d.example.ac.uk', 'server3.dept-f.example.ac.uk'], true) ? [['ip' => '10.0.0.1']] : [],
    );

    $result = $resolver->resolveBareHostname('server3', ['dept-d', 'dept-f', 'dept-g'], 'example.ac.uk');

    expect($result->status)->toBe(HostnameResolutionStatus::Ambiguous)
        ->and($result->fqdn)->toBeNull()
        ->and($result->resolved)->toBe(['server3.dept-d.example.ac.uk', 'server3.dept-f.example.ac.uk']);
});

it('pauses after each live lookup to stay polite to dns, but not for cached ones', function () {
    Sleep::fake();

    $resolver = new DnsResolver(
        resolver: fn (string $fqdn) => [],
        delaySeconds: 1,
    );

    $resolver->lookup('one.example.ac.uk');
    $resolver->lookup('two.example.ac.uk');
    $resolver->lookup('one.example.ac.uk');

    Sleep::assertSleptTimes(2);
});

it('does not pause when no delay is configured', function () {
    Sleep::fake();

    $resolver = new DnsResolver(resolver: fn (string $fqdn) => []);

    $resolver->lookup('one.example.ac.uk');
    $resolver->lookup('two.example.ac.uk');

    Sleep::assertNeverSlept();
});

it('defaults the candidate subdomains and domain from config when not given', function () {
    config([
        'patchmon.netbox.subdomains' => ['dept-a', 'dept-h'],
        'patchmon.netbox.default_domain' => 'example.ac.uk',
    ]);

    $resolver = new DnsResolver(
        resolver: fn (string $fqdn) => $fqdn === 'server1.dept-a.example.ac.uk' ? [['ip' => '10.0.0.1']] : [],
    );

    $result = $resolver->resolveBareHostname('server1');

    expect($result->status)->toBe(HostnameResolutionStatus::Accepted)
        ->and($result->fqdn)->toBe('server1.dept-a.example.ac.uk');
});
