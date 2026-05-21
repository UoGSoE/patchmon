<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('deletes a user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    Sanctum::actingAs($admin, ['admin:write']);

    $this->deleteJson("/api/v1/admin/users/{$target->id}")->assertNoContent();

    expect(User::find($target->id))->toBeNull();
});

it('refuses to delete the signed-in admin via the API', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->deleteJson("/api/v1/admin/users/{$admin->id}")
        ->assertStatus(422);

    expect(User::find($admin->id))->not->toBeNull();
});

it('reassigns authorship of servers the deleted user created over to the deleting admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    $team = Team::factory()->create();
    $server = Server::factory()->forTeam($team, $target)->create();
    Sanctum::actingAs($admin, ['admin:write']);

    $this->deleteJson("/api/v1/admin/users/{$target->id}")->assertNoContent();

    expect($server->fresh()->created_by_user_id)->toBe($admin->id);
});
