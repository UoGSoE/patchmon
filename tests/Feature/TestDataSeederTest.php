<?php

use App\Models\PatchEvent;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\TestDataSeeder;

it('runs the TestDataSeeder cleanly and produces a usable local dataset', function () {
    $this->seed(TestDataSeeder::class);

    expect(User::count())->toBeGreaterThanOrEqual(2)
        ->and(User::oversightAdmins()->count())->toBeGreaterThan(0)
        ->and(Team::count())->toBeGreaterThanOrEqual(2)
        ->and(Server::count())->toBeGreaterThan(0)
        ->and(PatchEvent::count())->toBeGreaterThan(0)
        ->and(Server::whereNotNull('alerting_since')->count())->toBeGreaterThan(0)
        ->and(Server::whereNotNull('silenced_until')->count())->toBeGreaterThan(0)
        ->and(Server::whereNull('team_id')->count())->toBeGreaterThan(0)
        ->and(Server::whereNull('team_id')->where('created_at', '<=', now()->subWeek())->count())->toBeGreaterThan(0)
        ->and(Server::whereNotNull('netbox_id')->count())->toBeGreaterThan(0)
        ->and(Server::where('is_virtual', true)->count())->toBeGreaterThan(0)
        ->and(Server::whereNotNull('inactive_since')->count())->toBeGreaterThan(0)
        ->and(Server::whereNotNull('patch_token_provisioned_at')->count())->toBeGreaterThan(0)
        // The never-checked-in signal exists (the NetBox triage imports)...
        ->and(Server::neverCheckedIn()->count())->toBeGreaterThan(0)
        // ...but silenced servers carry a patch history, so they don't pollute it.
        ->and(Server::neverCheckedIn()->whereNotNull('silenced_until')->count())->toBe(0);
});
