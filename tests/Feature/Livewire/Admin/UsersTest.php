<?php

use App\Livewire\Admin\Users;
use App\Models\Server;
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

it('edits a user via the user-form flyout', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create([
        'username' => 'old1x',
        'forenames' => 'Old',
        'surname' => 'Name',
        'email' => 'old@example.test',
    ]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openEdit', $target->id)
        ->set('form.username', 'new2y')
        ->set('form.forenames', 'New')
        ->set('form.surname', 'NewName')
        ->set('form.email', 'new@example.test')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $target->fresh();
    expect($fresh->username)->toBe('new2y')
        ->and($fresh->forenames)->toBe('New')
        ->and($fresh->surname)->toBe('NewName')
        ->and($fresh->email)->toBe('new@example.test');
});

it('rejects an edit that would duplicate another user email', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['email' => 'taken@example.test']);
    $target = User::factory()->create(['email' => 'mine@example.test']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openEdit', $target->id)
        ->set('form.email', 'taken@example.test')
        ->call('save')
        ->assertHasErrors(['form.email']);

    expect($target->fresh()->email)->toBe('mine@example.test');
});

it('rejects an edit that would duplicate another user username', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['username' => 'abc1d']);
    $target = User::factory()->create(['username' => 'xyz9z']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openEdit', $target->id)
        ->set('form.username', 'abc1d')
        ->call('save')
        ->assertHasErrors(['form.username']);

    expect($target->fresh()->username)->toBe('xyz9z');
});

it('rejects an edit with a non-GUID-shaped username', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['username' => 'abc1d']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openEdit', $target->id)
        ->set('form.username', '1234567z')
        ->call('save')
        ->assertHasErrors(['form.username']);

    expect($target->fresh()->username)->toBe('abc1d');
});

it('deletes a user with no personal jobs after typed confirmation', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['forenames' => 'Quiet', 'surname' => 'Leaver']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('confirmDelete', $target->id)
        ->set('typedConfirmation', 'Quiet Leaver')
        ->call('deleteWithServers')
        ->assertHasNoErrors();

    expect(User::find($target->id))->toBeNull();
});

it('transfers personal jobs to another user on delete', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    $recipient = User::factory()->create();
    $server = Server::factory()->forUser($target)->create();

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('confirmDelete', $target->id)
        ->set('transferTargetUserId', $recipient->id)
        ->call('transferAndDelete')
        ->assertHasNoErrors();

    expect(User::find($target->id))->toBeNull()
        ->and($server->fresh()->user_id)->toBe($recipient->id);
});

it('creates a new user via the user-form flyout', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openCreate')
        ->set('form.username', 'kmc2y')
        ->set('form.forenames', 'Kit')
        ->set('form.surname', 'McAuthor')
        ->set('form.email', 'kit@example.test')
        ->call('save')
        ->assertHasNoErrors();

    $created = User::where('username', 'kmc2y')->first();
    expect($created)->not->toBeNull()
        ->and($created->forenames)->toBe('Kit')
        ->and($created->surname)->toBe('McAuthor')
        ->and($created->email)->toBe('kit@example.test')
        ->and($created->is_admin)->toBeFalse()
        ->and($created->is_staff)->toBeTrue();
});

it('rejects creating a user with a duplicate username', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['username' => 'abc1d']);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openCreate')
        ->set('form.username', 'abc1d')
        ->set('form.forenames', 'Kit')
        ->set('form.surname', 'McAuthor')
        ->set('form.email', 'kit@example.test')
        ->call('save')
        ->assertHasErrors(['form.username']);

    expect(User::where('username', 'abc1d')->count())->toBe(1)
        ->and(User::where('email', 'kit@example.test')->count())->toBe(0);
});

it('rejects creating a user with a non-GUID-shaped username', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('openCreate')
        ->set('form.username', '1234567z')
        ->set('form.forenames', 'Kit')
        ->set('form.surname', 'McAuthor')
        ->set('form.email', 'kit@example.test')
        ->call('save')
        ->assertHasErrors(['form.username']);

    expect(User::where('email', 'kit@example.test')->count())->toBe(0);
});

it('refuses to delete the signed-in admin even via confirmDelete', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test(Users::class)
        ->call('confirmDelete', $admin->id);

    expect(User::find($admin->id))->not->toBeNull();
});
