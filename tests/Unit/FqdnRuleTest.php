<?php

use App\Rules\Fqdn;

function validateFqdn(mixed $value): bool
{
    $passed = true;
    (new Fqdn)->validate('name', $value, function () use (&$passed) {
        $passed = false;
    });

    return $passed;
}

it('accepts a simple two-label FQDN', function () {
    expect(validateFqdn('example.com'))->toBeTrue();
});

it('rejects a single-label name', function () {
    expect(validateFqdn('dc1'))->toBeFalse();
});

it('accepts a multi-label FQDN with hyphens and digits', function () {
    expect(validateFqdn('web-01.prod.example.ac.uk'))->toBeTrue();
});

it('rejects a label that starts or ends with a hyphen', function () {
    expect(validateFqdn('-foo.example.com'))->toBeFalse();
    expect(validateFqdn('foo-.example.com'))->toBeFalse();
});

it('rejects labels containing invalid characters', function () {
    expect(validateFqdn('foo bar.example.com'))->toBeFalse();
    expect(validateFqdn('foo_bar.example.com'))->toBeFalse();
});

it('rejects leading, trailing or consecutive dots', function () {
    expect(validateFqdn('.example.com'))->toBeFalse();
    expect(validateFqdn('example.com.'))->toBeFalse();
    expect(validateFqdn('foo..example.com'))->toBeFalse();
});

it('rejects labels longer than 63 characters', function () {
    expect(validateFqdn(str_repeat('a', 64).'.example.com'))->toBeFalse();
    expect(validateFqdn(str_repeat('a', 63).'.example.com'))->toBeTrue();
});

it('rejects total length over 253 characters', function () {
    expect(validateFqdn(str_repeat('a.', 130).'example.com'))->toBeFalse();
});

it('rejects non-string values', function () {
    expect(validateFqdn(null))->toBeFalse();
    expect(validateFqdn(12345))->toBeFalse();
    expect(validateFqdn(['example.com']))->toBeFalse();
    expect(validateFqdn(''))->toBeFalse();
});

it('accepts uppercase letters (callers lowercase on save)', function () {
    expect(validateFqdn('DC1.Eng.Example.AC.UK'))->toBeTrue();
});
