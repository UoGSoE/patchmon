<?php

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Models\ActivityLog;
use App\Models\Server;

it('reveals an existing server patch token once and stamps it, without recording a patch', function () {
    $server = Server::factory()->create(['name' => 'web01.example.com']);

    $response = $this->postJson('/record-patch/provision', ['fqdn' => 'web01.example.com']);

    $response->assertOk()
        ->assertExactJson(['patch_token' => $server->patch_token]);

    $server->refresh();
    expect($server->patch_token_provisioned_at)->not->toBeNull()
        ->and($server->patchEvents)->toHaveCount(0);
});

it('logs an automated activity row when an existing server is provisioned', function () {
    $server = Server::factory()->create(['name' => 'prov01.example.com']);

    $this->postJson('/record-patch/provision', ['fqdn' => 'prov01.example.com'])->assertOk();

    $log = ActivityLog::sole();
    expect($log->user_id)->toBeNull();
    expect($log->actorLabel())->toBe('Automated');
    expect($log->server_id)->toBe($server->id);
    expect($log->description)->toContain('provisioned');
});

it('logs both auto-create and provision when an unknown fqdn provisions', function () {
    $this->postJson('/record-patch/provision', ['fqdn' => 'fresh01.example.com'])->assertOk();

    $server = Server::firstWhere('name', 'fresh01.example.com');
    $logs = ActivityLog::orderBy('id')->get();

    expect($logs)->toHaveCount(2);
    expect($logs->every(fn ($log) => $log->user_id === null))->toBeTrue();
    expect($logs->every(fn ($log) => $log->server_id === $server->id))->toBeTrue();
    expect($logs[0]->description)->toContain('auto-created');
    expect($logs[1]->description)->toContain('provisioned');
});

it('refuses a second provision for the same fqdn with a 409, leaving the token and stamp untouched', function () {
    $server = Server::factory()->provisioned()->create(['name' => 'web01.example.com']);
    $originalToken = $server->patch_token;
    $originalStamp = $server->patch_token_provisioned_at;

    $response = $this->postJson('/record-patch/provision', ['fqdn' => 'web01.example.com']);

    $response->assertStatus(409)
        ->assertJsonStructure(['message']);

    $server->refresh();
    expect($server->patch_token)->toBe($originalToken)
        ->and($server->patch_token_provisioned_at->equalTo($originalStamp))->toBeTrue();
});

it('creates an unknown fqdn as a triage server with default cadence and reveals its token', function () {
    config([
        'patchmon.triage_defaults.interval_months' => 3,
        'patchmon.triage_defaults.grace_value' => 2,
        'patchmon.triage_defaults.grace_units' => 'weeks',
    ]);

    $response = $this->postJson('/record-patch/provision', ['fqdn' => 'NEWBOX01.example.com']);

    $response->assertOk()->assertJsonStructure(['patch_token']);

    expect(Server::count())->toBe(1);

    $server = Server::firstOrFail();
    expect($server->name)->toBe('newbox01.example.com')
        ->and($server->team_id)->toBeNull()
        ->and($server->created_by_user_id)->toBeNull()
        ->and($server->netbox_id)->toBeNull()
        ->and($server->os_type)->toBe(OsType::Other)
        ->and($server->interval_months)->toBe(3)
        ->and($server->grace_value)->toBe(2)
        ->and($server->grace_units)->toBe(GraceUnit::Weeks)
        ->and($server->patch_token_provisioned_at)->not->toBeNull()
        ->and($response->json('patch_token'))->toBe($server->patch_token);
});

it('applies an os_type hint when creating a triage server from an unknown fqdn', function () {
    $this->postJson('/record-patch/provision', [
        'fqdn' => 'winbox01.example.com',
        'os_type' => 'windows',
    ])->assertOk();

    expect(Server::firstOrFail()->os_type)->toBe(OsType::Windows);
});

it('falls back to Other and still provisions when the os_type hint is unrecognised', function () {
    $this->postJson('/record-patch/provision', [
        'fqdn' => 'mystery01.example.com',
        'os_type' => 'plan9',
    ])->assertOk();

    expect(Server::firstOrFail()->os_type)->toBe(OsType::Other);
});

it('does not clobber an existing server os_type with a provision hint', function () {
    $server = Server::factory()->create([
        'name' => 'known01.example.com',
        'os_type' => OsType::Linux,
    ]);

    $this->postJson('/record-patch/provision', [
        'fqdn' => 'known01.example.com',
        'os_type' => 'windows',
    ])->assertOk();

    expect($server->fresh()->os_type)->toBe(OsType::Linux);
});

it('rejects an invalid fqdn with a 422 and creates no server', function () {
    $response = $this->postJson('/record-patch/provision', ['fqdn' => 'not-a-hostname']);

    $response->assertStatus(422)->assertJsonValidationErrors('fqdn');

    expect(Server::count())->toBe(0);
});

it('rate-limits repeated provision calls from the same ip with a 429', function () {
    foreach (range(1, 10) as $i) {
        $this->postJson('/record-patch/provision', ['fqdn' => "box{$i}.example.com"])->assertOk();
    }

    $this->postJson('/record-patch/provision', ['fqdn' => 'box11.example.com'])
        ->assertStatus(429);
});
