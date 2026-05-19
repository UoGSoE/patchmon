<?php

use App\Models\CheckIn;
use App\Models\Job;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\TestDataSeeder;

it('runs the TestDataSeeder cleanly and produces a usable local dataset', function () {
    $this->seed(TestDataSeeder::class);

    expect(User::count())->toBeGreaterThanOrEqual(3)
        ->and(Team::count())->toBeGreaterThanOrEqual(2)
        ->and(Job::count())->toBeGreaterThan(0)
        ->and(CheckIn::count())->toBeGreaterThan(0)
        ->and(Job::whereNotNull('alerting_since')->count())->toBeGreaterThan(0)
        ->and(Job::whereNotNull('silenced_until')->count())->toBeGreaterThan(0)
        ->and(Team::whereNotNull('silenced_until')->count())->toBeGreaterThan(0);
});
