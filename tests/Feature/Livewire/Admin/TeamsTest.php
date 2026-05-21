<?php

use App\Livewire\Admin\Teams;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('updates an existing team when opened for edit', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['name' => 'Old name']);

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('openEdit', $team->id)
        ->set('editing.name', 'Storage')
        ->call('save')
        ->assertHasNoErrors();

    expect($team->fresh()->name)->toBe('Storage');
});

it('deletes a team that owns no servers', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->set('deletingId', $team->id)
        ->call('deleteEmpty');

    expect(Team::find($team->id))->toBeNull();
});

it('transfers a team\'s servers to another team and deletes the original', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $doomed = Team::factory()->create(['name' => 'Doomed']);
    $target = Team::factory()->create(['name' => 'Target']);
    $bystander = Team::factory()->create(['name' => 'Bystander']);

    $doomedServers = Server::factory()->count(2)->forTeam($doomed)->create();
    $bystanderServer = Server::factory()->forTeam($bystander)->create();

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->set('deletingId', $doomed->id)
        ->set('transferTargetTeamId', $target->id)
        ->call('transferAndDelete');

    expect(Team::find($doomed->id))->toBeNull();

    foreach ($doomedServers as $server) {
        expect($server->fresh()->team_id)->toBe($target->id);
    }

    expect($bystanderServer->fresh()->team_id)->toBe($bystander->id);
});

it('deletes a team and its servers when the typed confirmation matches the team name', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $doomed = Team::factory()->create(['name' => 'Doomed']);
    $servers = Server::factory()->count(2)->forTeam($doomed)->create();

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->set('deletingId', $doomed->id)
        ->set('typedConfirmation', 'Doomed')
        ->call('deleteWithServers');

    expect(Team::find($doomed->id))->toBeNull();
    foreach ($servers as $server) {
        expect(Server::find($server->id))->toBeNull();
    }
});

it('does not delete a team when the typed confirmation does not match', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $doomed = Team::factory()->create(['name' => 'Doomed']);
    $server = Server::factory()->forTeam($doomed)->create();

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->set('deletingId', $doomed->id)
        ->set('typedConfirmation', 'Doomd')
        ->call('deleteWithServers');

    expect(Team::find($doomed->id))->not->toBeNull()
        ->and(Server::find($server->id))->not->toBeNull();
});

it('lets an admin create a new team via the modal', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->set('editing.name', 'Network Services')
        ->set('editing.notification_email', 'netservices@example.ac.uk')
        ->call('save')
        ->assertHasNoErrors();

    expect(Team::firstWhere('name', 'Network Services'))->not->toBeNull();
});
