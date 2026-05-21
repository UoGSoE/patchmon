<?php

use App\Livewire\ServerDetail;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('unsilences the server when a team member flips the toggle off', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->silenced()->create();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->set('silenced', false);

    $server->refresh();
    expect($server->silenced_until)->toBeNull()
        ->and($server->silence_reason)->toBeNull();
});

it('silences the server when a team member flips the toggle on', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->create();
    $until = now()->addDay()->startOfSecond();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->set('silenceUntil', $until->toDateTimeLocalString())
        ->set('silenceReason', 'Power works')
        ->set('silenced', true);

    $server->refresh();
    expect($server->silenced_until)->not->toBeNull()
        ->and($server->silence_reason)->toBe('Power works');
});

it('saves changes to until and reason while already silenced', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->silenced()->create();
    $newUntil = now()->addDays(3)->startOfSecond();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->set('silenceUntil', $newUntil->toDateTimeLocalString())
        ->set('silenceReason', 'Extended works');

    $server->refresh();
    expect($server->silenced_until->startOfSecond()->equalTo($newUntil))->toBeTrue()
        ->and($server->silence_reason)->toBe('Extended works');
});

it('deletes the server when a team member confirms and redirects to home', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->create();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->call('delete')
        ->assertRedirect(route('home'));

    expect(Server::find($server->id))->toBeNull();
});

it('forbids a stranger from viewing a server in a team they are not in', function () {
    $stranger = User::factory()->create();
    $team = Team::factory()->create();
    $server = Server::factory()->forTeam($team)->create();

    $this->actingAs($stranger)
        ->get(route('servers.show', $server))
        ->assertForbidden();
});

it('shows recent patches on the detail page', function () {
    $owner = User::factory()->create(['forenames' => 'Pat', 'surname' => 'Cher']);
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->create();
    $server->recordPatch($owner, 'Reboot needed after libssl upgrade');

    $this->actingAs($owner)
        ->get(route('servers.show', $server))
        ->assertOk()
        ->assertSee('Pat Cher')
        ->assertSee('Reboot needed after libssl upgrade');
});

it('edits the server via the openEdit flyout flow', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->create(['name' => 'Old name']);

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->call('openEdit')
        ->set('form.name', 'New name')
        ->call('save')
        ->assertHasNoErrors();

    expect($server->fresh()->name)->toBe('New name');
});

it('clears the location when the edit form sets it to null', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->create(['location' => 'Rankine']);

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->call('openEdit')
        ->set('form.location', null)
        ->call('save')
        ->assertHasNoErrors();

    expect($server->fresh()->location)->toBeNull();
});

it('rejects a record-patch attempt with a future date or over-long notes and records nothing', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->alerting()->create();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->set('patchNotes', str_repeat('x', 1001))
        ->set('patchedAt', now()->addHour()->format('Y-m-d\TH:i'))
        ->call('recordPatch')
        ->assertHasErrors(['patchNotes', 'patchedAt']);

    $server->refresh();
    expect($server->patchEvents)->toHaveCount(0)
        ->and($server->alerting_since)->not->toBeNull();
});

it('records a patch with notes via the Livewire form, attributed to the acting user, and clears the alerting state', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->alerting()->create();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->set('patchNotes', 'Had to reboot twice')
        ->call('recordPatch')
        ->assertHasNoErrors();

    $server->refresh();
    expect($server->patchEvents)->toHaveCount(1)
        ->and($server->patchEvents->first()->notes)->toBe('Had to reboot twice')
        ->and($server->patchEvents->first()->patched_by)->toBe($owner->id)
        ->and($server->alerting_since)->toBeNull()
        ->and($server->last_alerted_at)->toBeNull()
        ->and($server->last_patched_at)->not->toBeNull();
});

it('shows the server name, interval and record-patch URL to the team member', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->withInterval(3)->create([
        'name' => 'fileserver-prod-02',
    ]);

    $this->actingAs($owner)
        ->get(route('servers.show', $server))
        ->assertOk()
        ->assertSee('fileserver-prod-02')
        ->assertSee('Quarterly')
        ->assertSee($server->patch_token);
});
