<?php

use App\Models\PatchEvent;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\TestDataSeeder;

it('runs the TestDataSeeder cleanly and produces a usable local dataset', function () {
    $this->seed(TestDataSeeder::class);

    expect(User::count())->toBeGreaterThanOrEqual(3)
        ->and(Team::count())->toBeGreaterThanOrEqual(2)
        ->and(Server::count())->toBeGreaterThan(0)
        ->and(PatchEvent::count())->toBeGreaterThan(0)
        ->and(Server::whereNotNull('alerting_since')->count())->toBeGreaterThan(0)
        ->and(Server::whereNotNull('silenced_until')->count())->toBeGreaterThan(0)
        ->and(Team::whereNotNull('silenced_until')->count())->toBeGreaterThan(0);
});
