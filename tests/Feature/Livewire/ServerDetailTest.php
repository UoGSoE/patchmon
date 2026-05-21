<?php

use App\Livewire\ServerDetail;
use App\Models\Server;
use App\Models\User;
use Livewire\Livewire;

it('unsilences the job when the owner flips the toggle off', function () {
    $owner = User::factory()->create();
    $server = Server::factory()->forUser($owner)->silenced()->create();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->set('silenced', false);

    $server->refresh();
    expect($server->silenced_until)->toBeNull()
        ->and($server->silence_reason)->toBeNull();
});

it('silences the job when the owner flips the toggle on', function () {
    $owner = User::factory()->create();
    $server = Server::factory()->forUser($owner)->create();
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
    $server = Server::factory()->forUser($owner)->silenced()->create();
    $newUntil = now()->addDays(3)->startOfSecond();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->set('silenceUntil', $newUntil->toDateTimeLocalString())
        ->set('silenceReason', 'Extended works');

    $server->refresh();
    expect($server->silenced_until->startOfSecond()->equalTo($newUntil))->toBeTrue()
        ->and($server->silence_reason)->toBe('Extended works');
});

it('deletes the job when the owner confirms and redirects to home', function () {
    $owner = User::factory()->create();
    $server = Server::factory()->forUser($owner)->create();

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->call('delete')
        ->assertRedirect(route('home'));

    expect(Server::find($server->id))->toBeNull();
});

it('forbids a stranger from viewing someone elses personal job', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $server = Server::factory()->forUser($owner)->create();

    $this->actingAs($stranger)
        ->get(route('servers.show', $server))
        ->assertForbidden();
});

it('shows recent check-ins on the detail page', function () {
    $owner = User::factory()->create();
    $server = Server::factory()->forUser($owner)->create();
    $server->recordPatchEvent('203.0.113.42');

    $this->actingAs($owner)
        ->get(route('servers.show', $server))
        ->assertOk()
        ->assertSee('203.0.113.42');
});

it('edits the job via the openEdit flyout flow', function () {
    $owner = User::factory()->create();
    $server = Server::factory()->forUser($owner)->create(['name' => 'Old name']);

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
    $server = Server::factory()->forUser($owner)->create(['location' => 'Rankine']);

    Livewire::actingAs($owner)
        ->test(ServerDetail::class, ['server' => $server])
        ->call('openEdit')
        ->set('form.location', null)
        ->call('save')
        ->assertHasNoErrors();

    expect($server->fresh()->location)->toBeNull();
});

it('shows the job name, schedule and check-in URL to the owning user', function () {
    $owner = User::factory()->create();
    $server = Server::factory()->forUser($owner)->withCron('0 2 * * *')->create([
        'name' => 'Nightly backup',
    ]);

    $this->actingAs($owner)
        ->get(route('servers.show', $server))
        ->assertOk()
        ->assertSee('Nightly backup')
        ->assertSee('0 2 * * *')
        ->assertSee($server->patch_token);
});
