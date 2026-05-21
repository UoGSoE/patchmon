<?php

use App\Livewire\MySettings;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

it('mints an API token with the selected abilities', function () {
    $alice = User::factory()->create();

    Livewire::actingAs($alice)
        ->test(MySettings::class)
        ->call('openCreateToken')
        ->set('tokenName', 'laptop dev')
        ->set('tokenAbilities', ['servers:read', 'servers:write'])
        ->call('createToken')
        ->assertHasNoErrors()
        ->assertSet('lastCreatedToken', fn ($t) => is_string($t) && str_contains($t, '|'));

    $token = $alice->tokens()->first();
    expect($token)->not->toBeNull()
        ->and($token->name)->toBe('laptop dev')
        ->and($token->abilities)->toEqualCanonicalizing(['servers:read', 'servers:write']);
});

it('revokes one of the signed-in users tokens', function () {
    $alice = User::factory()->create();
    $alice->createToken('first');
    $second = $alice->createToken('second')->accessToken;

    Livewire::actingAs($alice)
        ->test(MySettings::class)
        ->call('confirmRevokeToken', $second->id)
        ->call('revokeToken')
        ->assertHasNoErrors();

    expect($alice->tokens()->pluck('name')->all())->toEqualCanonicalizing(['first']);
});

it('refuses to revoke another users token', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $bobsToken = $bob->createToken('bobs laptop')->accessToken;

    expect(fn () => Livewire::actingAs($alice)
        ->test(MySettings::class)
        ->call('confirmRevokeToken', $bobsToken->id))
        ->toThrow(ModelNotFoundException::class);

    expect($bob->tokens()->count())->toBe(1);
});

it('rejects creating a token with a name the same user already used', function () {
    $alice = User::factory()->create();
    $alice->createToken('laptop dev');

    Livewire::actingAs($alice)
        ->test(MySettings::class)
        ->call('openCreateToken')
        ->set('tokenName', 'laptop dev')
        ->set('tokenAbilities', ['servers:read'])
        ->call('createToken')
        ->assertHasErrors(['tokenName']);

    expect($alice->tokens()->count())->toBe(1);
});

it('allows two different users to use the same token name', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $alice->createToken('laptop');

    Livewire::actingAs($bob)
        ->test(MySettings::class)
        ->call('openCreateToken')
        ->set('tokenName', 'laptop')
        ->set('tokenAbilities', ['servers:read'])
        ->call('createToken')
        ->assertHasNoErrors();

    expect($bob->tokens()->pluck('name')->all())->toEqual(['laptop']);
});

it('rejects creating a token without a name or any abilities', function () {
    $alice = User::factory()->create();

    Livewire::actingAs($alice)
        ->test(MySettings::class)
        ->call('openCreateToken')
        ->set('tokenName', '')
        ->set('tokenAbilities', [])
        ->call('createToken')
        ->assertHasErrors(['tokenName', 'tokenAbilities']);

    expect($alice->tokens()->count())->toBe(0);
});
