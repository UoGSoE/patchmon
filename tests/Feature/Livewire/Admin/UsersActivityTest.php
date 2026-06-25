<?php

use App\Livewire\Admin\Users;
use App\Models\ActivityLog;
use App\Models\User;
use Livewire\Livewire;

it('logs admin-status changes naming the target user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['forenames' => 'Bob', 'surname' => 'Brown', 'is_admin' => false]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('toggleAdmin', $target->id);

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->server_id)->toBeNull();
    expect($log->description)->toContain('Bob Brown');
    expect($log->description)->toContain('admin');
});

it('logs oversight-status changes naming the target user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['forenames' => 'Bob', 'surname' => 'Brown', 'is_oversight_admin' => false]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('toggleOversightAdmin', $target->id);

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->description)->toContain('Bob Brown');
    expect($log->description)->toContain('oversight');
});

it('logs creating a user from the admin UI', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openCreate')
        ->set('form.username', 'newb1n')
        ->set('form.forenames', 'New')
        ->set('form.surname', 'Body')
        ->set('form.email', 'newbody@example.test')
        ->call('save')
        ->assertHasNoErrors();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->description)->toContain('New Body');
    expect($log->description)->toContain('Created');
});

it('logs updating a user from the admin UI', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['forenames' => 'Edit', 'surname' => 'Me']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openEdit', $target->id)
        ->set('form.surname', 'Changed')
        ->call('save')
        ->assertHasNoErrors();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->description)->toContain('Updated');
});

it('logs deleting a user from the admin UI', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['forenames' => 'Gone', 'surname' => 'Soon']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('confirmDelete', $target->id)
        ->set('typedConfirmation', 'Gone Soon')
        ->call('delete')
        ->assertHasNoErrors();

    expect(User::find($target->id))->toBeNull();
    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($admin->id);
    expect($log->description)->toContain('Gone Soon');
    expect($log->description)->toContain('Deleted');
});
