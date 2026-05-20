<?php

use App\Models\Job;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('transfers personal jobs and deletes the user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    $recipient = User::factory()->create();
    $job = Job::factory()->forUser($target)->create();
    Sanctum::actingAs($admin, ['admin:write']);

    $this->deleteJson("/api/v1/admin/users/{$target->id}", [
        'transfer_jobs_to' => $recipient->id,
    ])->assertNoContent();

    expect(User::find($target->id))->toBeNull()
        ->and($job->fresh()->user_id)->toBe($recipient->id);
});

it('cascades personal jobs when delete_personal_jobs is true', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    $job = Job::factory()->forUser($target)->create();
    Sanctum::actingAs($admin, ['admin:write']);

    $this->deleteJson("/api/v1/admin/users/{$target->id}", [
        'delete_personal_jobs' => true,
    ])->assertNoContent();

    expect(User::find($target->id))->toBeNull()
        ->and(Job::find($job->id))->toBeNull();
});

it('returns 422 when deleting a user with personal jobs and no flag set', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    Job::factory()->forUser($target)->create();
    Sanctum::actingAs($admin, ['admin:write']);

    $this->deleteJson("/api/v1/admin/users/{$target->id}")
        ->assertStatus(422);

    expect(User::find($target->id))->not->toBeNull();
});

it('refuses to delete the signed-in admin via the API', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin, ['admin:write']);

    $this->deleteJson("/api/v1/admin/users/{$admin->id}")
        ->assertStatus(422);

    expect(User::find($admin->id))->not->toBeNull();
});

it('deletes a user with no personal jobs', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create();
    Sanctum::actingAs($admin, ['admin:write']);

    $this->deleteJson("/api/v1/admin/users/{$target->id}")->assertNoContent();

    expect(User::find($target->id))->toBeNull();
});
