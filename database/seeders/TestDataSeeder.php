<?php

namespace Database\Seeders;

use App\Enums\GraceUnit;
use App\Enums\OsType;
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

        $this->createInfrastructureServers($teams['infra'], $admin);
        $this->createApplicationDevelopmentServers($teams['appdev'], $admin);
        $this->createResearchComputingServers($teams['research']);
        $this->createResilienceServers($teams['resilience']);
        $this->createFrontDeskServers($teams['frontdesk']);
        $this->createFulfilmentServers($teams['fulfilment']);

        $this->backfillPatchEvents();
    }

    /**
     * @return array{0: User, 1: User, 2: User}
     */
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

        $frontdesk = Team::factory()->create([
            'name' => 'Front Desk',
            'notification_email' => 'frontdesk@example.test',
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

    private function createInfrastructureServers(Team $team, User $admin): void
    {
        $members = $team->users()->get();

        // ~80 Linux servers — monthly patching, 1 week grace.
        for ($i = 1; $i <= 80; $i++) {
            Server::factory()->forTeam($team, $members->random())->create([
                'name' => sprintf('linux-srv-%03d.infra.example.test', $i),
                'description' => 'General-purpose Linux server.',
                'location' => self::LOCATIONS[$i % count(self::LOCATIONS)],
                'os_type' => OsType::Linux,
                'interval_months' => 1,
                'grace_value' => 7,
                'grace_units' => GraceUnit::Days,
                'last_patched_at' => now()->subDays(rand(1, 25)),
            ]);
        }

        // ~70 Windows servers — monthly patching, 1 week grace.
        for ($i = 1; $i <= 70; $i++) {
            Server::factory()->forTeam($team, $members->random())->create([
                'name' => sprintf('win-srv-%03d.infra.example.test', $i),
                'description' => 'Windows file or application server.',
                'location' => self::LOCATIONS[($i + 3) % count(self::LOCATIONS)],
                'os_type' => OsType::Windows,
                'interval_months' => 1,
                'grace_value' => 7,
                'grace_units' => GraceUnit::Days,
                'last_patched_at' => now()->subDays(rand(1, 25)),
            ]);
        }

        // ~30 AD domain controllers — quarterly cadence (DCs reboot rarely).
        for ($i = 1; $i <= 30; $i++) {
            if ($i === 3) {
                continue; // ad-dc-03 is silenced — see below.
            }

            Server::factory()->forTeam($team, $members->random())->create([
                'name' => sprintf('ad-dc-%02d.infra.example.test', $i),
                'description' => 'Active Directory domain controller.',
                'location' => self::DATA_CENTRE_LOCATIONS[$i % count(self::DATA_CENTRE_LOCATIONS)],
                'os_type' => OsType::Windows,
                'interval_months' => 3,
                'grace_value' => 2,
                'grace_units' => GraceUnit::Weeks,
                'last_patched_at' => now()->subWeeks(rand(2, 10)),
            ]);
        }

        // ad-dc-03 held still during the exam diet — visible near the top of the list.
        Server::factory()->forTeam($team, $members->random())->silenced()->create([
            'name' => 'ad-dc-03.infra.example.test',
            'description' => 'Active Directory domain controller.',
            'location' => self::DATA_CENTRE_LOCATIONS[3 % count(self::DATA_CENTRE_LOCATIONS)],
            'os_type' => OsType::Windows,
            'interval_months' => 3,
            'grace_value' => 2,
            'grace_units' => GraceUnit::Weeks,
            'silenced_until' => now()->addWeeks(3),
            'silence_reason' => 'Held during exam diet — no schema or GPO changes',
        ]);

        // ~20 network / storage devices — yearly firmware updates.
        for ($i = 1; $i <= 20; $i++) {
            Server::factory()->forTeam($team, $members->random())->create([
                'name' => sprintf('netdev-%02d.infra.example.test', $i),
                'description' => 'Switch, router, or storage controller.',
                'location' => self::LOCATIONS[($i * 2) % count(self::LOCATIONS)],
                'os_type' => OsType::Other,
                'interval_months' => 12,
                'grace_value' => 1,
                'grace_units' => GraceUnit::Months,
                'last_patched_at' => now()->subMonths(rand(2, 11)),
            ]);
        }

        // Two stuck Linux backups currently alerting.
        Server::factory()->forTeam($team, $members->random())->alerting()->create([
            'name' => 'fileserver-prod-02.infra.example.test',
            'description' => 'Patches stalled since the kernel CVE rollout — investigating.',
            'location' => 'MDR',
            'os_type' => OsType::Linux,
            'interval_months' => 1,
            'grace_value' => 7,
            'grace_units' => GraceUnit::Days,
            'last_patched_at' => now()->subMonths(2),
        ]);

        Server::factory()->forTeam($team, $admin)->silenced()->create([
            'name' => 'ad-dc-replica-02.infra.example.test',
            'description' => 'Active Directory replica — replication validation in progress.',
            'location' => 'DataVita',
            'os_type' => OsType::Windows,
            'interval_months' => 3,
            'grace_value' => 2,
            'grace_units' => GraceUnit::Weeks,
            'silenced_until' => now()->addWeeks(2),
            'silence_reason' => 'Awaiting infosec sign-off after term-end audit',
        ]);

        // One silenced because the server is being decommissioned.
        Server::factory()->forTeam($team, $members->random())->silenced()->create([
            'name' => 'linux-srv-legacy-mailrelay.infra.example.test',
            'description' => 'Being decommissioned next month — alerts off.',
            'location' => 'Boyd Orr',
            'os_type' => OsType::Linux,
            'interval_months' => 1,
            'grace_value' => 7,
            'grace_units' => GraceUnit::Days,
            'silence_reason' => 'Decommissioning at month-end',
        ]);
    }

    private function createApplicationDevelopmentServers(Team $team, User $admin): void
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

        foreach ($apps as $app) {
            $creator = $members->random();
            $slug = strtolower(str_replace(' ', '-', $app));

            // Web frontend — Linux, monthly.
            Server::factory()->forTeam($team, $creator)->create([
                'name' => "{$slug}-web-01.appdev.example.test",
                'description' => "{$app} public web frontend.",
                'os_type' => OsType::Linux,
                'interval_months' => 1,
                'grace_value' => 7,
                'grace_units' => GraceUnit::Days,
                'last_patched_at' => now()->subDays(rand(1, 25)),
            ]);

            // Database — Linux, monthly with tighter grace.
            Server::factory()->forTeam($team, $creator)->create([
                'name' => "{$slug}-db-01.appdev.example.test",
                'description' => "{$app} database server.",
                'os_type' => OsType::Linux,
                'interval_months' => 1,
                'grace_value' => 5,
                'grace_units' => GraceUnit::Days,
                'last_patched_at' => now()->subDays(rand(1, 20)),
            ]);

            // Worker — varies between Linux and Windows.
            Server::factory()->forTeam($team, $creator)->create([
                'name' => "{$slug}-worker-01.appdev.example.test",
                'description' => "{$app} background workers.",
                'os_type' => fake()->randomElement([OsType::Linux, OsType::Windows]),
                'interval_months' => 1,
                'grace_value' => 7,
                'grace_units' => GraceUnit::Days,
                'last_patched_at' => now()->subDays(rand(1, 25)),
            ]);
        }

        // One stuck Windows patch deploy.
        Server::factory()->forTeam($team, $admin)->alerting()->create([
            'name' => 'conference-portal-payments-01.appdev.example.test',
            'description' => 'Stuck on a problematic .NET hotfix — vendor case open.',
            'location' => 'DataVita',
            'os_type' => OsType::Windows,
            'interval_months' => 1,
            'grace_value' => 7,
            'grace_units' => GraceUnit::Days,
            'last_patched_at' => now()->subDays(40),
        ]);

        // One silenced for a planned change freeze.
        Server::factory()->forTeam($team, $members->random())->silenced()->create([
            'name' => 'timetabling-web-staging-01.appdev.example.test',
            'description' => 'Frozen until end-of-term release.',
            'os_type' => OsType::Linux,
            'interval_months' => 1,
            'grace_value' => 7,
            'grace_units' => GraceUnit::Days,
            'silence_reason' => 'Change freeze until term-end',
        ]);

        // Silenced over the exam diet — no changes to student-facing systems.
        Server::factory()->forTeam($team, $members->random())->silenced()->create([
            'name' => 'exams-portal-db-01.appdev.example.test',
            'description' => 'Online exams portal database.',
            'location' => 'MDR',
            'os_type' => OsType::Linux,
            'interval_months' => 1,
            'grace_value' => 5,
            'grace_units' => GraceUnit::Days,
            'silenced_until' => now()->addWeeks(3),
            'silence_reason' => 'Exam period — no changes or patches until results are released',
        ]);
    }

    private function createResearchComputingServers(Team $team): void
    {
        $members = $team->users()->get();
        $clusters = ['beowulf', 'cetus', 'orca'];

        foreach ($clusters as $cluster) {
            // Login node — patched quarterly.
            Server::factory()->forTeam($team, $members->random())->create([
                'name' => "{$cluster}-login-01.research.example.test",
                'description' => "{$cluster} cluster login node.",
                'location' => 'MDR',
                'os_type' => OsType::Linux,
                'interval_months' => 3,
                'grace_value' => 2,
                'grace_units' => GraceUnit::Weeks,
                'last_patched_at' => now()->subWeeks(rand(2, 10)),
            ]);

            // Scheduler — patched quarterly.
            Server::factory()->forTeam($team, $members->random())->create([
                'name' => "{$cluster}-sched-01.research.example.test",
                'description' => "{$cluster} Slurm scheduler.",
                'location' => 'MDR',
                'os_type' => OsType::Linux,
                'interval_months' => 3,
                'grace_value' => 2,
                'grace_units' => GraceUnit::Weeks,
                'last_patched_at' => now()->subWeeks(rand(2, 10)),
            ]);

            // Compute nodes — twice-yearly (researchers really don't like reboots).
            for ($i = 1; $i <= 25; $i++) {
                Server::factory()->forTeam($team, $members->random())->create([
                    'name' => sprintf('%s-node-%03d.research.example.test', $cluster, $i),
                    'description' => "{$cluster} compute node.",
                    'location' => 'MDR',
                    'os_type' => OsType::Linux,
                    'interval_months' => 6,
                    'grace_value' => 1,
                    'grace_units' => GraceUnit::Months,
                    'last_patched_at' => now()->subMonths(rand(1, 5)),
                ]);
            }
        }

        // GPFS storage controllers — Other (appliance), quarterly.
        for ($i = 1; $i <= 4; $i++) {
            Server::factory()->forTeam($team, $members->random())->create([
                'name' => sprintf('gpfs-ctrl-%02d.research.example.test', $i),
                'description' => 'GPFS storage controller appliance.',
                'location' => 'MDR',
                'os_type' => OsType::Other,
                'interval_months' => 3,
                'grace_value' => 2,
                'grace_units' => GraceUnit::Weeks,
                'last_patched_at' => now()->subWeeks(rand(2, 10)),
            ]);
        }

        // One silenced — vendor patch window in progress.
        Server::factory()->forTeam($team, $members->random())->silenced()->create([
            'name' => 'orca-node-099.research.example.test',
            'description' => 'GPU node — vendor firmware update window.',
            'location' => 'MDR',
            'os_type' => OsType::Linux,
            'interval_months' => 6,
            'grace_value' => 1,
            'grace_units' => GraceUnit::Months,
            'silence_reason' => 'Vendor firmware window — alerts off till Monday',
        ]);

        // A GPU node held still while a research group finalises a conference paper.
        Server::factory()->forTeam($team, $members->random())->silenced()->create([
            'name' => 'cetus-node-gpu-07.research.example.test',
            'description' => 'GPU node reserved for the vision group.',
            'location' => 'MDR',
            'os_type' => OsType::Linux,
            'interval_months' => 6,
            'grace_value' => 1,
            'grace_units' => GraceUnit::Months,
            'silenced_until' => now()->addWeeks(2),
            'silence_reason' => 'Lab busy with conference paper submission — no reboots until camera-ready',
        ]);
    }

    private function createResilienceServers(Team $team): void
    {
        $members = $team->users()->get();

        $names = [
            'monitor-prom-01',
            'monitor-prom-02',
            'monitor-graf-01',
            'monitor-loki-01',
            'monitor-alerts-01',
            'backup-verify-01',
            'backup-verify-02',
            'failover-test-01',
            'failover-test-02',
            'dr-replica-orch-01',
            'dr-replica-orch-02',
            'sec-scan-01',
            'sec-scan-02',
            'ups-monitor-01',
            'thermal-monitor-01',
        ];

        foreach ($names as $name) {
            Server::factory()->forTeam($team, $members->random())->create([
                'name' => "{$name}.resilience.example.test",
                'description' => 'Resilience and DR monitoring infrastructure.',
                'location' => self::DATA_CENTRE_LOCATIONS[array_rand(self::DATA_CENTRE_LOCATIONS)],
                'os_type' => OsType::Linux,
                'interval_months' => 1,
                'grace_value' => 7,
                'grace_units' => GraceUnit::Days,
                'last_patched_at' => now()->subDays(rand(1, 25)),
            ]);
        }

        // One critical monitor currently alerting.
        Server::factory()->forTeam($team, $members->random())->alerting()->create([
            'name' => 'monitor-alerts-prod-02.resilience.example.test',
            'description' => 'Patches not running since the firewall change this morning.',
            'location' => 'DataVita',
            'os_type' => OsType::Linux,
            'interval_months' => 1,
            'grace_value' => 7,
            'grace_units' => GraceUnit::Days,
            'last_patched_at' => now()->subDays(45),
        ]);
    }

    private function createFrontDeskServers(Team $team): void
    {
        $members = $team->users()->get();

        $names = [
            'helpdesk-app-01',
            'helpdesk-db-01',
            'kiosk-01',
            'kiosk-02',
            'queue-display-01',
        ];

        foreach ($names as $name) {
            Server::factory()->forTeam($team, $members->random())->create([
                'name' => "{$name}.frontdesk.example.test",
                'description' => 'Front desk service support.',
                'os_type' => str_starts_with($name, 'kiosk') ? OsType::Windows : OsType::Linux,
                'interval_months' => 1,
                'grace_value' => 7,
                'grace_units' => GraceUnit::Days,
                'last_patched_at' => now()->subDays(rand(1, 25)),
            ]);
        }
    }

    private function createFulfilmentServers(Team $team): void
    {
        $members = $team->users()->get();

        $names = [
            'orders-app-01',
            'orders-db-01',
            'stock-app-01',
            'stock-db-01',
            'shipping-gateway-01',
            'shipping-gateway-02',
            'returns-portal-01',
            'invoicing-01',
            'invoicing-02',
            'reporting-warehouse-01',
        ];

        foreach ($names as $name) {
            Server::factory()->forTeam($team, $members->random())->create([
                'name' => "{$name}.fulfilment.example.test",
                'description' => 'Fulfilment service infrastructure.',
                'os_type' => str_contains($name, 'shipping') ? OsType::Windows : OsType::Linux,
                'interval_months' => 1,
                'grace_value' => 7,
                'grace_units' => GraceUnit::Days,
                'last_patched_at' => now()->subDays(rand(1, 25)),
            ]);
        }
    }

    private function backfillPatchEvents(): void
    {
        // Pick a few users who might historically have attributed patches.
        $attributableUsers = User::query()->where('is_staff', true)->where('is_admin', false)->take(5)->get();

        Server::query()
            ->whereNull('alerting_since')
            ->whereNotNull('last_patched_at')
            ->cursor()
            ->each(function (Server $server) use ($attributableUsers): void {
                $events = [
                    ['patched_at' => $server->last_patched_at->copy()->subMonths(2)],
                    ['patched_at' => $server->last_patched_at->copy()->subMonth()],
                    ['patched_at' => $server->last_patched_at],
                ];

                foreach ($events as $i => $attrs) {
                    $attribute = rand(1, 100) <= 60;

                    PatchEvent::factory()->for($server)->create([
                        ...$attrs,
                        'patched_by' => $attribute ? $attributableUsers->random()->id : null,
                        'notes' => $i === 2 && rand(1, 100) <= 15
                            ? fake()->randomElement([
                                'Required a second reboot — service start order needs review.',
                                'Microcode update applied.',
                                'Pinned package list updated.',
                                'Rolled back; vendor issuing replacement.',
                            ])
                            : null,
                    ]);
                }
            });
    }
}
