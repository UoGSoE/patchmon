<?php

use App\Models\Team;
use App\Models\User;

it('persists a team with its email defaults', function () {
    $team = Team::factory()->create([
        'name' => 'Network Services',
        'notification_email' => 'netservices@example.ac.uk',
        'sender_email' => 'noreply-net@example.ac.uk',
    ]);

    $team->refresh();

    expect($team->name)->toBe('Network Services')
        ->and($team->notification_email)->toBe('netservices@example.ac.uk')
        ->and($team->sender_email)->toBe('noreply-net@example.ac.uk');
});

it('can have users as members and a user can be in multiple teams', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $netservices = Team::factory()->create();
    $storage = Team::factory()->create();

    $netservices->users()->attach([$alice->id, $bob->id]);
    $storage->users()->attach($alice->id);

    expect($netservices->users->pluck('id'))->toContain($alice->id, $bob->id)
        ->and($storage->users->pluck('id'))->toContain($alice->id)->not->toContain($bob->id)
        ->and($alice->teams->pluck('id'))->toContain($netservices->id, $storage->id)
        ->and($bob->teams->pluck('id'))->toContain($netservices->id)->not->toContain($storage->id);
});
