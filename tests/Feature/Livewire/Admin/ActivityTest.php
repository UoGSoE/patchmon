<?php

use App\Livewire\Admin\Activity;
use App\Models\ActivityLog;
use App\Models\Server;
use App\Models\User;
use Livewire\Livewire;

it('renders /admin/activity for an admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get(route('admin.activity.index'))->assertOk();
});

it('refuses /admin/activity for a non-admin', function () {
    $alice = User::factory()->create(['is_admin' => false]);

    $this->actingAs($alice)->get(route('admin.activity.index'))->assertStatus(403);
});

it('filters entries to a single server when serverId is set', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $server = Server::factory()->create();

    ActivityLog::factory()->forServer($server)->create(['description' => 'On this server']);
    ActivityLog::factory()->create(['description' => 'Elsewhere']);

    Livewire::actingAs($admin)
        ->test(Activity::class, ['serverId' => $server->id])
        ->assertSee('On this server')
        ->assertDontSee('Elsewhere');
});

it('searches free-text over the user and server name snapshots', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $alice = User::factory()->create(['forenames' => 'Alice', 'surname' => 'Anderson']);

    ActivityLog::factory()->forUser($alice)->create(['description' => 'Alice did something']);
    ActivityLog::factory()->create(['user_name' => 'Bob Brown', 'description' => 'Bob did something']);

    Livewire::actingAs($admin)
        ->test(Activity::class)
        ->set('search', 'Anderson')
        ->assertSee('Alice did something')
        ->assertDontSee('Bob did something');
});
