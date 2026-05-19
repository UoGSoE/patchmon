<?php

namespace Database\Seeders;

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Models\CheckIn;
use App\Models\Job;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        [$admin, $standardUser, $extraUser] = $this->createUsers();
        [$netServices, $storage] = $this->createTeams($admin, $standardUser, $extraUser);
        $this->createJobs($admin, $standardUser, $extraUser, $netServices, $storage);
    }

    private function createUsers(): array
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

        $extraUser = User::factory()->create([
            'username' => 'user3x',
            'email' => 'user3x@example.test',
            'password' => bcrypt('secret'),
            'is_admin' => false,
            'forenames' => 'Sam',
            'surname' => 'McTechnician',
        ]);

        return [$admin, $standardUser, $extraUser];
    }

    private function createTeams(User $admin, User $standardUser, User $extraUser): array
    {
        $netServices = Team::factory()->create([
            'name' => 'Network Services',
            'notification_email' => 'netservices@example.test',
            'sender_email' => 'noreply-net@example.test',
        ]);

        $storage = Team::factory()->silenced()->create([
            'name' => 'Storage',
            'notification_email' => 'storage@example.test',
            'silence_reason' => 'Building C electrical works this weekend',
        ]);

        $netServices->users()->attach([$admin->id, $standardUser->id]);
        $storage->users()->attach([$standardUser->id, $extraUser->id]);

        return [$netServices, $storage];
    }

    private function createJobs(
        User $admin,
        User $standardUser,
        User $extraUser,
        Team $netServices,
        Team $storage,
    ): void {
        Job::factory()
            ->forTeam($netServices, $admin)
            ->create([
                'name' => 'Nightly DNS export',
                'description' => 'Pushes DNS zone exports to the backup server',
                'schedule_interval' => ScheduleInterval::Daily,
                'schedule_frequency' => 1,
                'grace_value' => 30,
                'grace_units' => GraceUnit::Minutes,
                'last_checked_in_at' => now()->subHours(8),
            ]);

        Job::factory()
            ->forTeam($netServices, $standardUser)
            ->withCron('0 */4 * * *')
            ->alerting()
            ->create([
                'name' => 'Switch config snapshot',
                'description' => 'Every four hours; alerting because the secondary controller is flaky',
                'grace_value' => 30,
                'grace_units' => GraceUnit::Minutes,
            ]);

        Job::factory()
            ->forTeam($storage, $extraUser)
            ->create([
                'name' => 'Weekly tape rotation reminder',
                'schedule_interval' => ScheduleInterval::Weekly,
                'schedule_frequency' => 1,
                'grace_value' => 2,
                'grace_units' => GraceUnit::Days,
            ]);

        Job::factory()
            ->forUser($admin)
            ->create([
                'name' => 'Personal home NAS backup',
                'schedule_interval' => ScheduleInterval::Daily,
                'grace_value' => 6,
                'grace_units' => GraceUnit::Hours,
                'last_checked_in_at' => now()->subHours(2),
            ]);

        $silencedPersonal = Job::factory()
            ->forUser($standardUser)
            ->silenced()
            ->create([
                'name' => 'Lab printer toner check',
                'schedule_interval' => ScheduleInterval::Weekly,
                'grace_value' => 1,
                'grace_units' => GraceUnit::Days,
            ]);

        Job::all()
            ->where('alerting_since', null)
            ->reject(fn (Job $job) => $job->is($silencedPersonal))
            ->each(function (Job $job): void {
                CheckIn::factory()
                    ->count(3)
                    ->for($job)
                    ->sequence(
                        ['checked_in_at' => now()->subDays(2)],
                        ['checked_in_at' => now()->subDay()],
                        ['checked_in_at' => now()->subHours(6)],
                    )
                    ->create();
            });
    }
}
