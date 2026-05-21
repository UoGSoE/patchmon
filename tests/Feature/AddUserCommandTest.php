<?php

use App\Models\User;

it('rejects a non-GUID-shaped username in args mode', function () {
    $this->artisan('patchmon:add-user', [
        'username' => '1234567z',
        'email' => 'kit@example.test',
        'surname' => 'McAuthor',
        'forenames' => 'Kit',
    ])->assertFailed();

    expect(User::where('email', 'kit@example.test')->count())->toBe(0);
});

it('rejects a duplicate username in args mode', function () {
    User::factory()->create(['username' => 'abc1d']);

    $this->artisan('patchmon:add-user', [
        'username' => 'abc1d',
        'email' => 'kit@example.test',
        'surname' => 'McAuthor',
        'forenames' => 'Kit',
    ])->assertFailed();

    expect(User::where('email', 'kit@example.test')->count())->toBe(0);
});

it('walks the interactive prompts and infers names from the email', function () {
    $this->artisan('patchmon:add-user')
        ->expectsQuestion('Email', 'jed.murphy.2@example.test')
        ->expectsQuestion('SSO username', 'jed1y')
        ->expectsQuestion('Forenames', 'Jed')
        ->expectsQuestion('Surname', 'Murphy')
        ->expectsConfirmation('Make this user an admin?', 'yes')
        ->assertSuccessful();

    $user = User::firstWhere('username', 'jed1y');
    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('jed.murphy.2@example.test')
        ->and($user->forenames)->toBe('Jed')
        ->and($user->surname)->toBe('Murphy')
        ->and($user->is_admin)->toBeTrue();
});

it('creates a user from positional arguments', function () {
    $this->artisan('patchmon:add-user', [
        'username' => 'kmc2y',
        'email' => 'kit@example.test',
        'surname' => 'McAuthor',
        'forenames' => 'Kit',
        '--admin' => true,
    ])->assertSuccessful();

    $user = User::firstWhere('username', 'kmc2y');
    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('kit@example.test')
        ->and($user->forenames)->toBe('Kit')
        ->and($user->surname)->toBe('McAuthor')
        ->and($user->is_admin)->toBeTrue()
        ->and($user->is_staff)->toBeTrue();
});
