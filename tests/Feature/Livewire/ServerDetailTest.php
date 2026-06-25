<?php

use App\Livewire\ServerDetail;
use App\Models\ActivityLog;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('shows an admin a link to the activity log filtered to this server', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $server = Server::factory()->create();

    Livewire::actingAs($admin)
        ->test(ServerDetail::class, ['server' => $server])
        ->assertSee(route('admin.activity.index', ['server' => $server->id]), escape: false);
});

it('does not show a non-admin the activity log link', function () {
    $owner = User::factory()->create(['is_admin' => false]);
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->create();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->assertDontSee(route('admin.activity.index', ['server' => $server->id]), escape: false);
});

it('logs the acting user when a server is silenced from the detail page', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->create();
    $start = now();
    $end = now()->addWeek();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->set('silenceUntil', $start->format('Y-m-d').'/'.$end->format('Y-m-d'))
        ->set('silenced', true);

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($owner->id);
    expect($log->server_id)->toBe($server->id);
    expect($log->description)->toStartWith('Silenced the server');
});

it('unsilences the server when a team member flips the toggle off', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->silenced()->create();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->set('silenced', false);

    $server->refresh();
    expect($server->silenced_from)->toBeNull()
        ->and($server->silenced_until)->toBeNull()
        ->and($server->silence_reason)->toBeNull();
});

it('silences the server with the picked start and end when a preset delivers the production array payload', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->silenced()->create();
    $start = now();
    $end = now()->addDays(7);

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->set('silenceUntil', [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'preset' => 'next7Days',
        ]);

    $server->refresh();
    expect($server->silenced_from->toDateTimeString())->toBe($start->copy()->startOfDay()->toDateTimeString())
        ->and($server->silenced_until->toDateTimeString())->toBe($end->copy()->endOfDay()->toDateTimeString());
});

it('silences the server through the picked range when a team member flips the toggle on', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->create();
    $start = now();
    $picked = now()->addDay();
    $range = $start->toDateString().'/'.$picked->toDateString();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->set('silenceUntil', $range)
        ->set('silenceReason', 'Power works')
        ->set('silenced', true);

    $server->refresh();
    expect($server->silenced_from->toDateTimeString())->toBe($start->copy()->startOfDay()->toDateTimeString())
        ->and($server->silenced_until->toDateTimeString())->toBe($picked->copy()->endOfDay()->toDateTimeString())
        ->and($server->silence_reason)->toBe('Power works');
});

it('schedules a future silence when the picker start is in the future and leaves the server currently un-silenced', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->create();
    $start = now()->addDays(7);
    $end = now()->addDays(14);

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->set('silenceUntil', ['start' => $start->toDateString(), 'end' => $end->toDateString()])
        ->set('silenceReason', 'Exam window')
        ->set('silenced', true);

    $server->refresh();
    expect($server->silenced_from->toDateTimeString())->toBe($start->copy()->startOfDay()->toDateTimeString())
        ->and($server->silenced_until->toDateTimeString())->toBe($end->copy()->endOfDay()->toDateTimeString())
        ->and($server->isCurrentlySilenced())->toBeFalse();
});

it('saves changes to until and reason while already silenced', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->silenced()->create();
    $newUntil = now()->addDays(3);
    $range = now()->toDateString().'/'.$newUntil->toDateString();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->set('silenceUntil', $range)
        ->set('silenceReason', 'Extended works');

    $server->refresh();
    expect($server->silenced_until->toDateTimeString())->toBe($newUntil->copy()->endOfDay()->toDateTimeString())
        ->and($server->silence_reason)->toBe('Extended works');
});

it('regenerating the token rotates it, clears the provisioning stamp, and leaves the server and its patches intact', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->provisioned()->create();
    $server->recordPatch($owner, 'patched before regenerating');
    $originalToken = $server->patch_token;

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->call('regenerateToken')
        ->assertHasNoErrors();

    $server->refresh();
    expect($server->patch_token)->not->toBe($originalToken)
        ->and($server->patch_token_provisioned_at)->toBeNull()
        ->and(Server::find($server->id))->not->toBeNull()
        ->and($server->patchEvents)->toHaveCount(1);
});

it('offers the regenerate control on every server, but only shows the provisioning date when provisioned', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $provisioned = Server::factory()->forTeam($team, $owner)->provisioned()->create();
    $plain = Server::factory()->forTeam($team, $owner)->create();

    $this->actingAs($owner)
        ->get(route('servers.show', $provisioned))
        ->assertOk()
        ->assertSee('Regenerate')
        ->assertSee('Token provisioned');

    $this->actingAs($owner)
        ->get(route('servers.show', $plain))
        ->assertOk()
        ->assertSee('Regenerate')
        ->assertDontSee('Token provisioned');
});

it('deletes the server when a team member confirms and redirects to home', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->create();
    $bystander = Server::factory()->forTeam($team)->create();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->call('delete')
        ->assertRedirect(route('home'));

    expect(Server::find($server->id))->toBeNull()
        ->and(Server::find($bystander->id))->not->toBeNull();
});

it('forbids a stranger from viewing a server in a team they are not in', function () {
    $stranger = User::factory()->create();
    $team = Team::factory()->create();
    $server = Server::factory()->forTeam($team)->create();

    $this->actingAs($stranger)
        ->get(route('servers.show', $server))
        ->assertForbidden();
});

it('lets a staff user view an unassigned server in triage', function () {
    $staff = User::factory()->staff()->create();
    $server = Server::factory()->unassigned()->create();

    $this->actingAs($staff)
        ->get(route('servers.show', $server))
        ->assertOk();
});

it('forbids a non-staff user from viewing an unassigned server', function () {
    $student = User::factory()->student()->create();
    $server = Server::factory()->unassigned()->create();

    $this->actingAs($student)
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
    $server = Server::factory()->forTeam($team, $owner)->create(['name' => 'old-name.example.test']);

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->call('openEdit')
        ->set('form.name', 'new-name.example.test')
        ->call('save')
        ->assertHasNoErrors();

    expect($server->fresh()->name)->toBe('new-name.example.test');
});

it('rejects an edit that sets the name to an invalid FQDN', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->create(['name' => 'keeper.example.test']);

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->call('openEdit')
        ->set('form.name', 'not-an-fqdn')
        ->call('save')
        ->assertHasErrors(['form.name']);

    expect($server->fresh()->name)->toBe('keeper.example.test');
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

it('claims a creatorless server for the editor on save', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->create(['created_by_user_id' => null]);

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->call('openEdit')
        ->set('form.name', 'claimed.example.test')
        ->call('save')
        ->assertHasNoErrors();

    expect($server->fresh()->created_by_user_id)->toBe($owner->id);
});

it('leaves an existing creator untouched when another member edits', function () {
    $creator = User::factory()->create();
    $editor = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach([$creator->id, $editor->id]);
    $server = Server::factory()->forTeam($team, $creator)->create(['name' => 'shared.example.test']);

    Livewire::actingAs($editor)
        ->test(ServerDetail::class, ['server' => $server])
        ->call('openEdit')
        ->set('form.name', 'edited.example.test')
        ->call('save')
        ->assertHasNoErrors();

    expect($server->fresh()->created_by_user_id)->toBe($creator->id);
});

it('lets the form set is_virtual on a server not linked to netbox', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->create(['name' => 'manual.example.test', 'is_virtual' => false]);

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->call('openEdit')
        ->set('form.is_virtual', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($server->fresh()->is_virtual)->toBeTrue();
});

it('does not let the form change is_virtual while netbox_id is set', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->fromNetbox(5)->create(['name' => 'synced.example.test']);

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->call('openEdit')
        ->set('form.is_virtual', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($server->fresh()->is_virtual)->toBeFalse();
});

it('re-enables is_virtual once netbox_id is cleared', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    $server = Server::factory()->forTeam($team, $owner)->fromNetbox(5)->create(['name' => 'unlinking.example.test']);

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->call('openEdit')
        ->set('form.netbox_id', null)
        ->set('form.is_virtual', true)
        ->call('save')
        ->assertHasNoErrors();

    $server->refresh();
    expect($server->netbox_id)->toBeNull()
        ->and($server->is_virtual)->toBeTrue();
});

it('rejects a netbox_id that collides with another synced server of the same kind', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    Server::factory()->forTeam($team, $owner)->fromNetbox(5, false)->create(['name' => 'already-synced.example.test']);
    $editing = Server::factory()->forTeam($team, $owner)->create(['name' => 'mine.example.test', 'is_virtual' => false]);

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $editing])
        ->call('openEdit')
        ->set('form.netbox_id', 5)
        ->call('save')
        ->assertHasErrors(['form.netbox_id']);

    expect($editing->fresh()->netbox_id)->toBeNull();
});

it('allows the same netbox_id for the other kind, since device and VM ids are independent', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($owner);
    Server::factory()->forTeam($team, $owner)->fromNetbox(5, false)->create(['name' => 'device-five.example.test']);
    $editingVm = Server::factory()->forTeam($team, $owner)->virtual()->create(['name' => 'my-vm.example.test']);

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $editingVm])
        ->call('openEdit')
        ->set('form.netbox_id', 5)
        ->call('save')
        ->assertHasNoErrors();

    $editingVm->refresh();
    expect($editingVm->netbox_id)->toBe(5)
        ->and($editingVm->is_virtual)->toBeTrue();
});
