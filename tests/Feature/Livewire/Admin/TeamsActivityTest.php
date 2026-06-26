<?php

use App\Livewire\Admin\Teams;
use App\Models\ActivityLog;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

it('logs creating a team', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->call('openCreate')
        ->set('editing.name', 'Platform')
        ->set('editing.notification_email', 'platform@example.test')
        ->call('save')
        ->assertHasNoErrors();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->server_id)->toBeNull();
    expect($log->description)->toContain('Platform');
    expect($log->description)->toContain('Created');
});

it('logs deleting an empty team, keeping the name in the description', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['name' => 'Disbanded']);

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->set('deletingId', $team->id)
        ->call('deleteEmpty');

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->description)->toContain('Disbanded');
    expect($log->description)->toContain('Deleted');
});

it('logs a team deletion that transfers its servers', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $doomed = Team::factory()->create(['name' => 'Old']);
    $target = Team::factory()->create(['name' => 'New']);
    Server::factory()->count(2)->forTeam($doomed)->create();

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->set('deletingId', $doomed->id)
        ->set('transferTargetTeamId', $target->id)
        ->call('transferAndDelete');

    $log = ActivityLog::sole();
    expect($log->description)->toContain('Old');
    expect($log->description)->toContain('New');
    expect($log->description)->toContain('2');
});

it('logs a team deletion that also deletes its servers', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $doomed = Team::factory()->create(['name' => 'Doomed']);
    Server::factory()->count(3)->forTeam($doomed)->create();

    Livewire::actingAs($admin)
        ->test(Teams::class)
        ->set('deletingId', $doomed->id)
        ->set('typedConfirmation', 'Doomed')
        ->call('deleteWithServers');

    $log = ActivityLog::sole();
    expect($log->description)->toContain('Doomed');
    expect($log->description)->toContain('3');
});
