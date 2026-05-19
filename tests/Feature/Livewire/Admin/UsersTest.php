<?php

use App\Livewire\Admin\Users;
use App\Models\Job;
use App\Models\User;
use Livewire\Livewire;

it('renders the users page through the HTTP layer for an admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->count(3)->create();

    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
});

it('toggles the admin flag on a user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['is_admin' => false]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('toggleAdmin', $target->id);

    expect($target->fresh()->is_admin)->toBeTrue();
});

it('does not let an admin demote themselves via toggleAdmin', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('toggleAdmin', $admin->id);

    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('does not render an admin toggle for the current user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $other = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->assertDontSeeHtml("toggleAdmin({$admin->id})")
        ->assertSeeHtml("toggleAdmin({$other->id})");
});

it('does not render a staff toggle anywhere on the page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->count(2)->create();

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->assertDontSeeHtml('toggleStaff');
});

it('edits a user via the edit flyout', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['forenames' => 'Old', 'surname' => 'Name', 'email' => 'old@example.test']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openEdit', $target->id)
        ->set('editing.forenames', 'New')
        ->set('editing.surname', 'NewName')
        ->set('editing.email', 'new@example.test')
        ->call('saveEdit')
        ->assertHasNoErrors();

    $fresh = $target->fresh();
    expect($fresh->forenames)->toBe('New')
        ->and($fresh->surname)->toBe('NewName')
        ->and($fresh->email)->toBe('new@example.test');
});

it('rejects an edit that would duplicate another user email', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $existing = User::factory()->create(['email' => 'taken@example.test']);
    $target = User::factory()->create(['email' => 'mine@example.test']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openEdit', $target->id)
        ->set('editing.email', 'taken@example.test')
        ->call('saveEdit')
        ->assertHasErrors(['editing.email']);

    expect($target->fresh()->email)->toBe('mine@example.test');
});

it('deletes a user with no personal jobs after typed confirmation', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['forenames' => 'Quiet', 'surname' => 'Leaver']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('confirmDelete', $target->id)
        ->set('typedConfirmation', 'Quiet Leaver')
        ->call('deleteWithJobs')
        ->assertHasNoErrors();

    expect(User::find($target->id))->toBeNull();
});

it('transfers personal jobs to another user on delete', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    $recipient = User::factory()->create();
    $job = Job::factory()->forUser($target)->create();

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('confirmDelete', $target->id)
        ->set('transferTargetUserId', $recipient->id)
        ->call('transferAndDelete')
        ->assertHasNoErrors();

    expect(User::find($target->id))->toBeNull()
        ->and($job->fresh()->user_id)->toBe($recipient->id);
});

it('refuses to delete the signed-in admin even via confirmDelete', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('confirmDelete', $admin->id);

    expect(User::find($admin->id))->not->toBeNull();
});
