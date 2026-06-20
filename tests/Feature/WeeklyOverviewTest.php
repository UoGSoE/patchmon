<?php

use App\Mail\WeeklyOverview;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('queues the weekly overview to the oversight admins', function () {
    Mail::fake();

    $oversightAdmin = User::factory()->oversightAdmin()->create();

    $this->artisan('patchmon:weekly-overview')->assertSuccessful();

    Mail::assertQueued(WeeklyOverview::class, fn (WeeklyOverview $mail) => $mail->hasTo($oversightAdmin->email));
});

it('sends nothing when there are no oversight admins', function () {
    Mail::fake();

    User::factory()->create(['is_oversight_admin' => false]);

    $this->artisan('patchmon:weekly-overview')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('shows an all-clear highlight when nothing is overdue', function () {
    Server::factory()->count(3)->forTeam(Team::factory()->create())->create();

    (new WeeklyOverview)->assertSeeInHtml('All servers are up to date');
});

it('lists overdue servers with their team under an overdue highlight', function () {
    $team = Team::factory()->create(['name' => 'Research Computing']);
    Server::factory()->forTeam($team)->overdue()->create(['name' => 'matlab-server1.example.test']);
    Server::factory()->forTeam($team)->create(['name' => 'healthy.example.test']);

    (new WeeklyOverview)
        ->assertSeeInHtml('1 server is overdue')
        ->assertSeeInHtml('matlab-server1.example.test')
        ->assertSeeInHtml('Research Computing')
        ->assertDontSeeInHtml('healthy.example.test');
});

it('caps the overdue table at five rows and summarises the rest', function () {
    $team = Team::factory()->create();

    // Five severely overdue (last patched 6 months ago) — most overdue, so listed.
    foreach (range(1, 5) as $i) {
        Server::factory()->forTeam($team)->create([
            'name' => "severe-{$i}.example.test",
            'last_patched_at' => now()->subMonths(6),
        ]);
    }

    // Two mildly overdue (last patched 2 months ago) — cut from the table, summarised.
    Server::factory()->forTeam($team)->create(['name' => 'mild-1.example.test', 'last_patched_at' => now()->subMonths(2)]);
    Server::factory()->forTeam($team)->create(['name' => 'mild-2.example.test', 'last_patched_at' => now()->subMonths(2)]);

    (new WeeklyOverview)
        ->assertSeeInHtml('7 servers are overdue')
        ->assertSeeInHtml('severe-1.example.test')
        ->assertSeeInHtml('and 2 others')
        ->assertDontSeeInHtml('mild-1.example.test');
});

it('links to the dashboard', function () {
    Server::factory()->forTeam(Team::factory()->create())->create();

    (new WeeklyOverview)
        ->assertSeeInHtml('View the dashboard')
        ->assertSeeInHtml(route('admin.dashboard'));
});
