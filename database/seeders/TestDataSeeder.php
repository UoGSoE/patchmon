<?php

namespace Database\Seeders;

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Models\PatchEvent;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Minimal placeholder seeder so the local-dev workflow has data.
 * The realistic patchmon seed (mixed OS / intervals / overdue / silenced
 * examples) is the work of patchmon-UkLWZ.1.3.
 */
class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin2x',
            'email' => 'admin2x@example.test',
            'password' => bcrypt('secret'),
            'is_admin' => true,
            'forenames' => 'Jenny',
            'surname' => 'MacAdmin',
        ]);

        $standardUser = User::factory()->create([
            'username' => 'user2x',
            'email' => 'user2x@example.test',
            'password' => bcrypt('secret'),
            'is_admin' => false,
            'forenames' => 'Olivia',
            'surname' => 'McUser',
        ]);

        $infra = Team::factory()->create([
            'name' => 'Infrastructure',
            'notification_email' => 'infrastructure@example.test',
        ]);

        $appdev = Team::factory()->create([
            'name' => 'Application Development',
            'notification_email' => 'appdev@example.test',
        ]);

        $infra->users()->attach([$admin->id]);
        $appdev->users()->attach([$admin->id, $standardUser->id]);

        // A pair of healthy servers per team.
        foreach ([$infra, $appdev] as $team) {
            Server::factory()->forTeam($team, $admin)->create([
                'name' => "{$team->name} server 1",
                'os_type' => OsType::Linux,
                'interval_months' => 1,
                'grace_value' => 7,
                'grace_units' => GraceUnit::Days,
                'last_patched_at' => now()->subWeek(),
            ]);

            Server::factory()->forTeam($team, $admin)->create([
                'name' => "{$team->name} server 2",
                'os_type' => OsType::Windows,
                'interval_months' => 3,
                'grace_value' => 2,
                'grace_units' => GraceUnit::Weeks,
                'last_patched_at' => now()->subWeeks(2),
            ]);
        }

        // One alerting and one silenced server so the home page has obvious examples.
        Server::factory()->forTeam($infra, $admin)->alerting()->create([
            'name' => 'fileserver-prod-02',
            'os_type' => OsType::Linux,
            'interval_months' => 1,
            'grace_value' => 7,
            'grace_units' => GraceUnit::Days,
            'last_patched_at' => now()->subMonths(2),
        ]);

        Server::factory()->forTeam($infra, $admin)->silenced()->create([
            'name' => 'legacy-mail-relay',
            'os_type' => OsType::Linux,
            'interval_months' => 1,
            'grace_value' => 7,
            'grace_units' => GraceUnit::Days,
        ]);

        // A handful of patch events so PatchEvent::count() is non-zero.
        Server::query()->whereNotNull('last_patched_at')->cursor()->each(function (Server $server) use ($admin): void {
            PatchEvent::factory()->count(2)->for($server)->create([
                'patched_by' => $admin->id,
            ]);
        });
    }
}
