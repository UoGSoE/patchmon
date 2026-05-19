<?php

use App\Livewire\Admin\Teams;
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

it('deletes a team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create();

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('delete', $team->id);

    expect(Team::find($team->id))->toBeNull();
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
