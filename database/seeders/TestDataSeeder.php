<?php

namespace Database\Seeders;

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Models\PatchEvent;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    private const LOCATIONS = [
        'JWS',
        'JWN',
        'Rankine',
        'Joseph Black',
        'Maths',
        'Boyd Orr',
        'MDR',
        'DataVita',
        'Saughfield',
    ];

    private const DATA_CENTRE_LOCATIONS = ['MDR', 'DataVita'];

    public function run(): void
    {
        [$admin, $standardUser, $extraUser] = $this->createNamedTestAccounts();
        $staff = $this->createSupportingStaff();
        $teams = $this->createTeams($admin, $standardUser, $extraUser, $staff);

        $this->createApplicationDevelopmentJobs($teams['appdev']);
        $this->createInfrastructureJobs($teams['infra']);
        $this->createResearchComputingJobs($teams['research']);
        $this->createResilienceJobs($teams['resilience']);
        $this->createFrontDeskJobs($teams['frontdesk']);
        $this->createFulfilmentJobs($teams['fulfilment']);
        $this->createPersonalJobs($admin, $standardUser);

        $this->backfillPatchEvents();
    }

    private function createNamedTestAccounts(): array
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

    private function createSupportingStaff(): Collection
    {
        return User::factory()->count(27)->staff()->create();
    }

    /**
     * @return array<string, Team>
     */
    private function createTeams(User $admin, User $standardUser, User $extraUser, Collection $staff): array
    {
        $infra = Team::factory()->create([
            'name' => 'Infrastructure',
            'notification_email' => 'infrastructure@example.test',
            'sender_email' => 'noreply-infra@example.test',
        ]);

        $appdev = Team::factory()->create([
            'name' => 'Application Development',
            'notification_email' => 'appdev@example.test',
            'sender_email' => 'noreply-appdev@example.test',
        ]);

        $research = Team::factory()->create([
            'name' => 'Research Computing',
            'notification_email' => 'research-computing@example.test',
        ]);

        $resilience = Team::factory()->create([
            'name' => 'Resilience & Business Continuity',
            'notification_email' => 'resilience@example.test',
        ]);

        $frontdesk = Team::factory()->silenced()->create([
            'name' => 'Front Desk',
            'notification_email' => 'frontdesk@example.test',
            'silence_reason' => 'Office closed for refurbishment until Monday',
        ]);

        $fulfilment = Team::factory()->create([
            'name' => 'Fulfilment',
            'notification_email' => 'fulfilment@example.test',
        ]);

        $infra->users()->attach($admin->id);
        $appdev->users()->attach([$admin->id, $standardUser->id]);
        $fulfilment->users()->attach($standardUser->id);
        $research->users()->attach($extraUser->id);
        $resilience->users()->attach($extraUser->id);

        $pool = $staff->shuffle()->values();
        $cursor = 0;
        $take = function (int $count) use ($pool, &$cursor): array {
            $slice = $pool->slice($cursor, $count);
            $cursor += $count;

            return $slice->pluck('id')->all();
        };

        $infra->users()->attach($take(8));
        $appdev->users()->attach($take(5));
        $research->users()->attach($take(4));
        $resilience->users()->attach($take(5));
        $frontdesk->users()->attach($take(2));
        $fulfilment->users()->attach($take(3));

        // A handful of staff sit on a second team — realistic for a small department.
        $appdev->users()->attach($pool->slice(0, 2)->pluck('id')->all());
        $resilience->users()->attach($pool->slice(8, 1)->pluck('id')->all());

        return [
            'infra' => $infra,
            'appdev' => $appdev,
            'research' => $research,
            'resilience' => $resilience,
            'frontdesk' => $frontdesk,
            'fulfilment' => $fulfilment,
        ];
    }

    private function createApplicationDevelopmentJobs(Team $team): void
    {
        $members = $team->users()->get();
        $apps = [
            'Timetabling',
            'Room Booking',
            'Library Portal',
            'Course Registration',
            'Staff Directory',
            'Expense Claims',
            'Hardware Requests',
            'Lab Booking',
            'Print Quota',
            'Conference Portal',
        ];

        foreach ($apps as $index => $app) {
            $createdBy = $members->random();

            Server::factory()->forTeam($team, $createdBy)
                ->withCron('* * * * *')
                ->create([
                    'name' => "{$app} — schedule:run heartbeat",
                    'description' => "Pings whenever the {$app} scheduler ticks.",
                    'grace_value' => 5,
                    'grace_units' => GraceUnit::Minutes,
                    'last_patched_at' => now()->subSeconds(30),
                ]);

            Server::factory()->forTeam($team, $createdBy)
                ->withCron('15 2 * * *')
                ->create([
                    'name' => "{$app} — nightly database backup",
                    'description' => 'Dumps the database and ships it to object storage.',
                    'grace_value' => 1,
                    'grace_units' => GraceUnit::Hours,
                    'last_patched_at' => now()->subMinutes(45),
                ]);

            Server::factory()->forTeam($team, $createdBy)
                ->withCron('30 3 * * *')
                ->create([
                    'name' => "{$app} — session and cache sweep",
                    'grace_value' => 30,
                    'grace_units' => GraceUnit::Minutes,
                    'last_patched_at' => now()->subMinutes(45),
                ]);

            Server::factory()->forTeam($team, $createdBy)
                ->withCron('0 * * * *')
                ->create([
                    'name' => "{$app} — Horizon snapshot",
                    'grace_value' => 15,
                    'grace_units' => GraceUnit::Minutes,
                    'last_patched_at' => now()->subMinutes(20),
                ]);

            // Half the apps also send a weekly digest.
            if ($index % 2 === 0) {
                Server::factory()->forTeam($team, $createdBy)
                    ->withCron('0 8 * * MON')
                    ->create([
                        'name' => "{$app} — weekly digest email",
                        'grace_value' => 2,
                        'grace_units' => GraceUnit::Hours,
                        'last_patched_at' => now()->subMinutes(45),
                    ]);
            }
        }

        // A failing payment reconciliation, two days into an alerting state.
        Server::factory()->forTeam($team, $members->random())
            ->withCron('15 2 * * *')
            ->alerting()
            ->create([
                'name' => 'Conference Portal — payment reconciliation',
                'description' => 'Failing since the Stripe key rotation; team are investigating.',
                'location' => 'DataVita',
                'grace_value' => 30,
                'grace_units' => GraceUnit::Minutes,
                'last_patched_at' => now()->subDays(2),
            ]);

        // One silenced for planned maintenance.
        Server::factory()->forTeam($team, $members->random())
            ->withCron('0 4 * * SUN')
            ->silenced()
            ->create([
                'name' => 'Print Quota — weekly Active Directory sync',
                'silence_reason' => 'AD upgrade window — alerts off until Tuesday',
                'location' => 'MDR',
                'grace_value' => 1,
                'grace_units' => GraceUnit::Hours,
                'last_patched_at' => now()->subDays(5),
            ]);
    }

    private function createInfrastructureJobs(Team $team): void
    {
        $members = $team->users()->get();

        // ~80 Linux servers — rsnapshot to the central backup pool.
        for ($i = 1; $i <= 80; $i++) {
            Server::factory()->forTeam($team, $members->random())
                ->withCron(sprintf('%d %d * * *', ($i * 7) % 60, 1 + ($i % 4)))
                ->create([
                    'name' => sprintf('linux-srv-%03d nightly backup', $i),
                    'description' => 'rsnapshot to the central backup pool.',
                    'location' => self::LOCATIONS[$i % count(self::LOCATIONS)],
                    'grace_value' => 1,
                    'grace_units' => GraceUnit::Hours,
                    'last_patched_at' => now()->subMinutes(30 + ($i % 20)),
                ]);
        }

        // ~70 Windows servers — VSS + offsite replication.
        for ($i = 1; $i <= 70; $i++) {
            Server::factory()->forTeam($team, $members->random())
                ->withCron(sprintf('%d %d * * *', ($i * 11) % 60, 22 + ($i % 3)))
                ->create([
                    'name' => sprintf('win-srv-%03d VSS backup', $i),
                    'description' => 'Volume Shadow Copy and offsite replication.',
                    'location' => self::LOCATIONS[($i + 3) % count(self::LOCATIONS)],
                    'grace_value' => 2,
                    'grace_units' => GraceUnit::Hours,
                    'last_patched_at' => now()->subMinutes(30 + ($i % 25)),
                ]);
        }

        // ~30 AD domain controllers — NTDS / system state backups.
        for ($i = 1; $i <= 30; $i++) {
            Server::factory()->forTeam($team, $members->random())
                ->withCron(sprintf('%d 23 * * *', ($i * 13) % 60))
                ->create([
                    'name' => sprintf('ad-dc-%02d system state backup', $i),
                    'description' => 'NTDS plus system state to the backup vault.',
                    'location' => self::DATA_CENTRE_LOCATIONS[$i % count(self::DATA_CENTRE_LOCATIONS)],
                    'grace_value' => 1,
                    'grace_units' => GraceUnit::Hours,
                    'last_patched_at' => now()->subMinutes(30 + ($i % 15)),
                ]);
        }

        // ~20 network and storage devices — config exports for diffing.
        for ($i = 1; $i <= 20; $i++) {
            Server::factory()->forTeam($team, $members->random())
                ->withCron(sprintf('%d 5 * * *', ($i * 17) % 60))
                ->create([
                    'name' => sprintf('netdev-%02d config export', $i),
                    'description' => 'Pulls the running config to git for diffing.',
                    'location' => self::LOCATIONS[($i * 2) % count(self::LOCATIONS)],
                    'grace_value' => 30,
                    'grace_units' => GraceUnit::Minutes,
                    'last_patched_at' => now()->subMinutes(25),
                ]);
        }

        // A couple of stuck backups to demo the alerting view.
        Server::factory()->forTeam($team, $members->random())
            ->withCron('25 22 * * *')
            ->alerting()
            ->create([
                'name' => 'fileserver-prod-02 VSS backup',
                'description' => 'Down since the SAN fabric switch flapped on Friday.',
                'location' => 'MDR',
                'grace_value' => 2,
                'grace_units' => GraceUnit::Hours,
                'last_patched_at' => now()->subDays(3),
            ]);

        Server::factory()->forTeam($team, $members->random())
            ->withCron('0 23 * * *')
            ->alerting()
            ->create([
                'name' => 'ad-dc-replica-02 system state backup',
                'description' => 'Service account password expired; ticket in flight.',
                'location' => 'DataVita',
                'grace_value' => 1,
                'grace_units' => GraceUnit::Hours,
                'last_patched_at' => now()->subDays(2),
            ]);

        // One silenced because the server is being decommissioned.
        Server::factory()->forTeam($team, $members->random())
            ->withCron('0 1 * * *')
            ->silenced()
            ->create([
                'name' => 'linux-srv-legacy-mailrelay nightly backup',
                'silence_reason' => 'Being decommissioned next month — alerts off.',
                'location' => 'Boyd Orr',
                'grace_value' => 1,
                'grace_units' => GraceUnit::Hours,
            ]);
    }

    private function createResearchComputingJobs(Team $team): void
    {
        $members = $team->users()->get();
        $clusters = ['beowulf', 'cetus', 'orca'];

        foreach ($clusters as $cluster) {
            $createdBy = $members->random();

            Server::factory()->forTeam($team, $createdBy)
                ->withCron('*/15 * * * *')
                ->create([
                    'name' => "{$cluster} — Slurm scheduler health",
                    'location' => 'MDR',
                    'grace_value' => 5,
                    'grace_units' => GraceUnit::Minutes,
                    'last_patched_at' => now()->subMinutes(4),
                ]);

            Server::factory()->forTeam($team, $createdBy)
                ->withCron('0 * * * *')
                ->create([
                    'name' => "{$cluster} — GPFS health check",
                    'location' => 'MDR',
                    'grace_value' => 10,
                    'grace_units' => GraceUnit::Minutes,
                    'last_patched_at' => now()->subMinutes(25),
                ]);

            Server::factory()->forTeam($team, $createdBy)
                ->withCron('0 4 * * *')
                ->create([
                    'name' => "{$cluster} — nightly metadata backup",
                    'location' => 'MDR',
                    'grace_value' => 1,
                    'grace_units' => GraceUnit::Hours,
                    'last_patched_at' => now()->subMinutes(45),
                ]);

            Server::factory()->forTeam($team, $createdBy)
                ->withCron('0 6 * * MON')
                ->create([
                    'name' => "{$cluster} — weekly usage report",
                    'location' => 'MDR',
                    'grace_value' => 2,
                    'grace_units' => GraceUnit::Hours,
                    'last_patched_at' => now()->subMinutes(45),
                ]);
        }

        Server::factory()->forTeam($team, $members->random())
            ->withCron('*/5 * * * *')
            ->create([
                'name' => 'Research storage — replication lag check',
                'grace_value' => 5,
                'grace_units' => GraceUnit::Minutes,
                'last_patched_at' => now()->subMinutes(2),
            ]);

        Server::factory()->forTeam($team, $members->random())
            ->withCron('30 5 * * *')
            ->create([
                'name' => 'Software licence server — daily liveness probe',
                'grace_value' => 30,
                'grace_units' => GraceUnit::Minutes,
                'last_patched_at' => now()->subMinutes(40),
            ]);
    }

    private function createResilienceJobs(Team $team): void
    {
        $members = $team->users()->get();

        $weeklyReports = [
            'Matlab — weekly licence usage report',
            'SPSS — weekly licence usage report',
            'NVivo — weekly licence usage report',
            'MS Office — weekly activation report',
        ];

        foreach ($weeklyReports as $name) {
            Server::factory()->forTeam($team, $members->random())
                ->withCron('0 7 * * MON')
                ->create([
                    'name' => $name,
                    'grace_value' => 4,
                    'grace_units' => GraceUnit::Hours,
                    'last_patched_at' => now()->subMinutes(45),
                ]);
        }

        $dailyVerifications = [
            'Backup verification — file servers',
            'Backup verification — VMware estate',
            'Backup verification — databases',
            'Backup verification — Microsoft 365',
            'Patch compliance — Windows fleet',
            'Patch compliance — Linux fleet',
            'Endpoint A/V signature audit',
            'UPS battery self-report',
        ];

        foreach ($dailyVerifications as $name) {
            Server::factory()->forTeam($team, $members->random())
                ->withCron('30 6 * * *')
                ->create([
                    'name' => $name,
                    'grace_value' => 2,
                    'grace_units' => GraceUnit::Hours,
                    'last_patched_at' => now()->subMinutes(45),
                ]);
        }

        $criticalServices = [
            'Critical service ping — student records',
            'Critical service ping — finance ledger',
            'Critical service ping — HR self-service',
            'Critical service ping — VLE',
            'Critical service ping — library catalogue',
            'Critical service ping — research data store',
        ];

        foreach ($criticalServices as $name) {
            Server::factory()->forTeam($team, $members->random())
                ->withCron('*/10 * * * *')
                ->create([
                    'name' => $name,
                    'grace_value' => 5,
                    'grace_units' => GraceUnit::Minutes,
                    'last_patched_at' => now()->subMinutes(3),
                ]);
        }

        $weeklyDrills = [
            'Failover test — internal wiki',
            'Failover test — staff file share',
            'Generator self-test',
            'Server room temperature snapshot',
            'DR replication health digest',
            'Offsite tape rotation reminder',
        ];

        foreach ($weeklyDrills as $name) {
            Server::factory()->forTeam($team, $members->random())
                ->withCron('0 9 * * WED')
                ->create([
                    'name' => $name,
                    'grace_value' => 2,
                    'grace_units' => GraceUnit::Hours,
                    'last_patched_at' => now()->subMinutes(45),
                ]);
        }

        // Critical service that has stopped pinging — alerting badge.
        Server::factory()->forTeam($team, $members->random())
            ->withCron('*/10 * * * *')
            ->alerting()
            ->create([
                'name' => 'Critical service ping — payroll',
                'description' => 'Has not pinged since the firewall rule change this morning.',
                'grace_value' => 5,
                'grace_units' => GraceUnit::Minutes,
                'last_patched_at' => now()->subHours(3),
            ]);
    }

    private function createFrontDeskJobs(Team $team): void
    {
        $members = $team->users()->get();

        Server::factory()->forTeam($team, $members->random())
            ->withCron('0 8 * * MON-FRI')
            ->create([
                'name' => 'Ticket queue daily digest',
                'grace_value' => 30,
                'grace_units' => GraceUnit::Minutes,
                'last_patched_at' => now()->subMinutes(40),
            ]);

        Server::factory()->forTeam($team, $members->random())
            ->withCron('30 17 * * FRI')
            ->create([
                'name' => 'Weekly walk-up KPI export',
                'grace_value' => 1,
                'grace_units' => GraceUnit::Hours,
                'last_patched_at' => now()->subMinutes(45),
            ]);

        Server::factory()->forTeam($team, $members->random())
            ->withCron('45 7 * * *')
            ->create([
                'name' => 'After-hours voicemail digest',
                'grace_value' => 15,
                'grace_units' => GraceUnit::Minutes,
                'last_patched_at' => now()->subMinutes(10),
            ]);
    }

    private function createFulfilmentJobs(Team $team): void
    {
        $members = $team->users()->get();

        Server::factory()->forTeam($team, $members->random())
            ->withCron('0 7 * * MON')
            ->create([
                'name' => 'Weekly order status poll',
                'description' => 'curl against the order-tracking API; emails the team summary.',
                'grace_value' => 2,
                'grace_units' => GraceUnit::Hours,
                'last_patched_at' => now()->subMinutes(45),
            ]);

        Server::factory()->forTeam($team, $members->random())
            ->withCron('0 23 * * *')
            ->create([
                'name' => 'Stock reconciliation',
                'grace_value' => 1,
                'grace_units' => GraceUnit::Hours,
                'last_patched_at' => now()->subMinutes(40),
            ]);

        Server::factory()->forTeam($team, $members->random())
            ->withCron('*/30 * * * *')
            ->create([
                'name' => 'Shipping API health probe',
                'grace_value' => 10,
                'grace_units' => GraceUnit::Minutes,
                'last_patched_at' => now()->subMinutes(8),
            ]);
    }

    private function createPersonalJobs(User $admin, User $standardUser): void
    {
        Server::factory()->forUser($admin)->create([
            'name' => 'Personal home NAS backup',
            'location' => 'Home',
            'schedule_interval' => ScheduleInterval::Daily,
            'grace_value' => 6,
            'grace_units' => GraceUnit::Hours,
            'last_patched_at' => now()->subHours(2),
        ]);

        Server::factory()->forUser($standardUser)->silenced()->create([
            'name' => 'Lab printer toner check',
            'schedule_interval' => ScheduleInterval::Weekly,
            'grace_value' => 1,
            'grace_units' => GraceUnit::Days,
        ]);
    }

    private function backfillPatchEvents(): void
    {
        Server::query()
            ->whereNull('alerting_since')
            ->whereNotNull('last_patched_at')
            ->cursor()
            ->each(function (Server $server): void {
                PatchEvent::factory()
                    ->count(3)
                    ->for($server)
                    ->sequence(
                        ['patched_at' => now()->subDays(2)],
                        ['patched_at' => now()->subDay()],
                        ['patched_at' => $server->last_patched_at],
                    )
                    ->create();
            });
    }
}
