<?php

use App\Mail\UnassignedServersDigest;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('queues a single digest of long-unassigned servers to the oversight admins', function () {
    Mail::fake();

    $oversightAdmin = User::factory()->oversightAdmin()->create();

    $longUnassigned = Server::factory()->unassigned()->create(['name' => 'triage-old.example.test']);
    $longUnassigned->created_at = now()->subWeeks(2);
    $longUnassigned->save();

    $freshlyUnassigned = Server::factory()->unassigned()->create(['name' => 'triage-fresh.example.test']);
    $freshlyUnassigned->created_at = now()->subDays(2);
    $freshlyUnassigned->save();

    $assigned = Server::factory()->forTeam(Team::factory()->create())->create(['name' => 'assigned.example.test']);
    $assigned->created_at = now()->subWeeks(3);
    $assigned->save();

    $this->artisan('patchmon:alert-unassigned')->assertSuccessful();

    Mail::assertQueued(UnassignedServersDigest::class, 1);
    Mail::assertQueued(UnassignedServersDigest::class, function (UnassignedServersDigest $mail) use ($oversightAdmin, $longUnassigned, $freshlyUnassigned, $assigned) {
        $listedIds = $mail->servers->pluck('id');

        return $mail->hasTo($oversightAdmin->email)
            && $listedIds->contains($longUnassigned->id)
            && ! $listedIds->contains($freshlyUnassigned->id)
            && ! $listedIds->contains($assigned->id);
    });
});

it('sends nothing when no server has been unassigned for over a week', function () {
    Mail::fake();

    User::factory()->oversightAdmin()->create();

    // Freshly unassigned — under a week (created_at defaults to now).
    Server::factory()->unassigned()->create();

    // Assigned, even though it is old.
    $assigned = Server::factory()->forTeam(Team::factory()->create())->create();
    $assigned->created_at = now()->subWeeks(3);
    $assigned->save();

    $this->artisan('patchmon:alert-unassigned')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('sends nothing when there are no oversight admins, even with a qualifying server', function () {
    Mail::fake();

    $longUnassigned = Server::factory()->unassigned()->create();
    $longUnassigned->created_at = now()->subWeeks(2);
    $longUnassigned->save();

    $this->artisan('patchmon:alert-unassigned')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('renders the digest body listing the unassigned servers', function () {
    $server = Server::factory()->unassigned()->create(['name' => 'triage-old.example.test']);
    $server->created_at = now()->subWeeks(2);
    $server->save();

    $mailable = new UnassignedServersDigest(Server::whereKey($server->id)->get());

    $mailable->assertSeeInHtml('triage-old.example.test')
        ->assertSeeInHtml('1 server has been sitting unassigned');
});
