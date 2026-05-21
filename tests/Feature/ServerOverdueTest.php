<?php

use App\Enums\GraceUnit;
use App\Models\Server;
use Illuminate\Support\Facades\Date;

it('is not overdue when a server was patched inside its interval plus grace', function () {
    $server = Server::factory()->create([
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
        'last_patched_at' => now()->subDays(20),
    ]);

    expect($server->isOverdue())->toBeFalse();
});

it('is overdue when a server was patched more than interval plus grace ago', function () {
    $server = Server::factory()->create([
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
        'last_patched_at' => now()->subDays(50),
    ]);

    expect($server->isOverdue())->toBeTrue();
});

it('counts a never-patched server from its created_at as the reference', function () {
    $server = Server::factory()->create([
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
        'last_patched_at' => null,
    ]);

    $server->created_at = now()->subDays(50);
    $server->save();

    expect($server->isOverdue())->toBeTrue();
});

it('respects a quarterly interval', function () {
    $server = Server::factory()->create([
        'interval_months' => 3,
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
        'last_patched_at' => now()->subMonths(2),
    ]);

    expect($server->isOverdue())->toBeFalse();
});

it('handles month-end gracefully — 31 Jan + 1 month + 2 weeks grace is mid-March', function () {
    Date::setTestNow('2026-03-15 12:00:00');

    $server = Server::factory()->create([
        'interval_months' => 1,
        'grace_value' => 2,
        'grace_units' => GraceUnit::Weeks,
        'last_patched_at' => Date::parse('2026-01-31 09:00:00'),
    ]);

    // Deadline: 31 Jan + 1 month (no overflow) = 28 Feb, + 2 weeks grace = 14 Mar.
    // On 15 Mar, the server is overdue.
    expect($server->deadline()->toDateString())->toBe('2026-03-14')
        ->and($server->isOverdue())->toBeTrue();
});

it('exposes deadline as a Carbon for the UI to render', function () {
    $server = Server::factory()->create([
        'interval_months' => 1,
        'grace_value' => 7,
        'grace_units' => GraceUnit::Days,
        'last_patched_at' => Date::parse('2026-05-01'),
    ]);

    expect($server->deadline()->toDateString())->toBe('2026-06-08');
});
