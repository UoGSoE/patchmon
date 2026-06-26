<?php

use App\Jobs\SyncNetboxServers;
use App\Livewire\ImportServers;
use App\Models\ActivityLog;
use App\Models\PatchEvent;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Ohffs\SimpleSpout\ExcelSheet;

function makeXlsxUpload(array $rows): UploadedFile
{
    $path = (new ExcelSheet)->generate($rows);

    return UploadedFile::fake()->createWithContent('servers.xlsx', file_get_contents($path));
}

it('renders the import page for a staff user', function () {
    $alice = User::factory()->create(['is_staff' => true]);

    $this->actingAs($alice)
        ->get(route('import'))
        ->assertOk();
});

it('previews a sheet of valid rows', function () {
    $alice = User::factory()->create(['is_staff' => true]);

    $file = makeXlsxUpload([
        ['server name', 'os', 'last patched'],
        ['web-01.example.test', 'linux', ''],
        ['db-01.example.test', 'windows', '2026-04-01'],
    ]);

    Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->set('file', $file)
        ->assertSet('previewed', true)
        ->assertCount('validRows', 2)
        ->assertCount('invalidRows', 0)
        ->assertCount('duplicateRows', 0);
});

it('puts rows with an invalid FQDN into invalidRows', function () {
    $alice = User::factory()->create(['is_staff' => true]);

    $file = makeXlsxUpload([
        ['server name', 'os', 'last patched'],
        ['ok.example.test', 'linux', ''],
        ['not-an-fqdn', 'linux', ''],
    ]);

    $component = Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->set('file', $file);

    expect($component->get('validRows'))->toHaveCount(1)
        ->and($component->get('invalidRows'))->toHaveCount(1)
        ->and($component->get('invalidRows')[0]['name'])->toBe('not-an-fqdn');
});

it('rejects rows with an unknown OS', function () {
    $alice = User::factory()->create(['is_staff' => true]);

    $file = makeXlsxUpload([
        ['server name', 'os', 'last patched'],
        ['srv-01.example.test', 'plan9', ''],
    ]);

    $component = Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->set('file', $file);

    expect($component->get('validRows'))->toHaveCount(0)
        ->and($component->get('invalidRows'))->toHaveCount(1)
        ->and($component->get('invalidRows')[0]['reason'])->toContain('OS');
});

it('rejects rows with a future last-patched date', function () {
    $alice = User::factory()->create(['is_staff' => true]);

    $file = makeXlsxUpload([
        ['server name', 'os', 'last patched'],
        ['srv-01.example.test', 'linux', now()->addMonth()->format('Y-m-d')],
    ]);

    $component = Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->set('file', $file);

    expect($component->get('validRows'))->toHaveCount(0)
        ->and($component->get('invalidRows'))->toHaveCount(1)
        ->and($component->get('invalidRows')[0]['reason'])->toContain('future');
});

it('moves rows whose FQDN already exists into duplicateRows', function () {
    $alice = User::factory()->create(['is_staff' => true]);
    $someTeam = Team::factory()->create();
    Server::factory()->forTeam($someTeam)->create(['name' => 'taken.example.test']);

    $file = makeXlsxUpload([
        ['server name', 'os', 'last patched'],
        ['fresh.example.test', 'linux', ''],
        ['taken.example.test', 'linux', ''],
    ]);

    $component = Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->set('file', $file);

    expect($component->get('validRows'))->toHaveCount(1)
        ->and($component->get('duplicateRows'))->toHaveCount(1)
        ->and($component->get('duplicateRows')[0]['name'])->toBe('taken.example.test');
});

it('accepts OS casing variants and trims whitespace on both columns', function () {
    $alice = User::factory()->create(['is_staff' => true]);

    $file = makeXlsxUpload([
        ['server name', 'os', 'last patched'],
        ['  one.example.test  ', 'LINUX', ''],
        ['two.example.test', '  Windows  ', ''],
        ['three.example.test', 'Other', ''],
    ]);

    $component = Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->set('file', $file);

    expect($component->get('validRows'))->toHaveCount(3)
        ->and($component->get('validRows')[0]['name'])->toBe('one.example.test')
        ->and($component->get('validRows')[0]['os_type'])->toBe('linux')
        ->and($component->get('validRows')[1]['os_type'])->toBe('windows');
});

it('treats the first row as data when firstRowIsHeader is false', function () {
    $alice = User::factory()->create(['is_staff' => true]);

    $file = makeXlsxUpload([
        ['one.example.test', 'linux', ''],
        ['two.example.test', 'windows', ''],
    ]);

    $component = Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->set('firstRowIsHeader', false)
        ->set('file', $file);

    expect($component->get('validRows'))->toHaveCount(2)
        ->and($component->get('validRows')[0]['name'])->toBe('one.example.test');
});

it('accepts an ISO-format string date in the last-patched column', function () {
    $alice = User::factory()->create(['is_staff' => true]);

    $file = makeXlsxUpload([
        ['server name', 'os', 'last patched'],
        ['dated.example.test', 'linux', '2026-04-15'],
    ]);

    $component = Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->set('file', $file);

    expect($component->get('validRows'))->toHaveCount(1)
        ->and($component->get('invalidRows'))->toHaveCount(0);
});

it('commits valid rows as servers in the chosen team with the chosen schedule', function () {
    $alice = User::factory()->create(['is_staff' => true]);
    $team = Team::factory()->create();
    $team->users()->attach($alice);

    $file = makeXlsxUpload([
        ['server name', 'os', 'last patched'],
        ['one.example.test', 'linux', ''],
        ['two.example.test', 'windows', ''],
    ]);

    Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->set('team_id', $team->id)
        ->set('interval_months', 3)
        ->set('grace_value', 2)
        ->set('grace_units', 'weeks')
        ->set('file', $file)
        ->call('confirm')
        ->assertHasNoErrors();

    expect(Server::count())->toBe(2);
    $one = Server::firstWhere('name', 'one.example.test');
    expect($one->team_id)->toBe($team->id)
        ->and($one->os_type->value)->toBe('linux')
        ->and($one->interval_months)->toBe(3)
        ->and($one->grace_value)->toBe(2)
        ->and($one->grace_units->value)->toBe('weeks')
        ->and($one->created_by_user_id)->toBe($alice->id);
});

it('logs a create activity row for each imported server', function () {
    $alice = User::factory()->create(['is_staff' => true]);
    $team = Team::factory()->create();
    $team->users()->attach($alice);

    $file = makeXlsxUpload([
        ['server name', 'os', 'last patched'],
        ['imp-one.example.test', 'linux', ''],
        ['imp-two.example.test', 'linux', ''],
    ]);

    Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->set('team_id', $team->id)
        ->set('interval_months', 1)
        ->set('grace_value', 7)
        ->set('grace_units', 'days')
        ->set('file', $file)
        ->call('confirm')
        ->assertHasNoErrors();

    $logs = ActivityLog::all();
    expect($logs)->toHaveCount(2);
    $logs->each(function ($log) use ($alice) {
        expect($log->user_id)->toBe($alice->id);
        expect($log->server_id)->not->toBeNull();
        expect($log->description)->toContain('imported');
    });
});

it('records a PatchEvent for each imported server with a last-patched date', function () {
    $alice = User::factory()->create(['is_staff' => true]);
    $team = Team::factory()->create();
    $team->users()->attach($alice);

    $file = makeXlsxUpload([
        ['server name', 'os', 'last patched'],
        ['dated.example.test', 'linux', '2026-04-15'],
        ['undated.example.test', 'linux', ''],
    ]);

    Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->set('team_id', $team->id)
        ->set('file', $file)
        ->call('confirm')
        ->assertHasNoErrors();

    $dated = Server::firstWhere('name', 'dated.example.test');
    $undated = Server::firstWhere('name', 'undated.example.test');

    expect($dated->patchEvents()->count())->toBe(1)
        ->and($dated->patchEvents()->first()->patched_at->toDateString())->toBe('2026-04-15')
        ->and($dated->patchEvents()->first()->patched_by)->toBeNull()
        ->and($dated->patchEvents()->first()->notes)->toBe('Imported from spreadsheet')
        ->and($dated->last_patched_at->toDateString())->toBe('2026-04-15')
        ->and($undated->patchEvents()->count())->toBe(0)
        ->and(PatchEvent::count())->toBe(1);
});

it('refuses to commit without a team selected', function () {
    $alice = User::factory()->create(['is_staff' => true]);

    $file = makeXlsxUpload([
        ['server name', 'os', 'last patched'],
        ['one.example.test', 'linux', ''],
    ]);

    Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->set('file', $file)
        ->call('confirm')
        ->assertHasErrors(['team_id']);

    expect(Server::count())->toBe(0);
});

it('rolls the whole commit back when one row fails to insert', function () {
    $alice = User::factory()->create(['is_staff' => true]);
    $team = Team::factory()->create();
    $team->users()->attach($alice);

    $file = makeXlsxUpload([
        ['server name', 'os', 'last patched'],
        ['ok-one.example.test', 'linux', ''],
        ['will-clash.example.test', 'linux', ''],
        ['ok-two.example.test', 'linux', ''],
    ]);

    $component = Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->set('team_id', $team->id)
        ->set('file', $file);

    // Between preview and confirm, slip in a row that will make 'will-clash' duplicate
    // when the transaction tries to insert it. This forces the rollback path.
    Server::factory()->forTeam($team)->create(['name' => 'will-clash.example.test']);

    expect(fn () => $component->call('confirm'))
        ->toThrow(QueryException::class);

    expect(Server::count())->toBe(1)
        ->and(Server::firstWhere('name', 'ok-one.example.test'))->toBeNull()
        ->and(Server::firstWhere('name', 'ok-two.example.test'))->toBeNull();
});

it('records a success summary after committing and resets the preview state', function () {
    $alice = User::factory()->create(['is_staff' => true]);
    $team = Team::factory()->create(['name' => 'Infrastructure']);
    $team->users()->attach($alice);
    Server::factory()->forTeam($team)->create(['name' => 'pre-existing.example.test']);

    $file = makeXlsxUpload([
        ['server name', 'os', 'last patched'],
        ['one.example.test', 'linux', ''],
        ['two.example.test', 'linux', ''],
        ['pre-existing.example.test', 'linux', ''],
        ['bad-name', 'linux', ''],
    ]);

    $component = Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->set('team_id', $team->id)
        ->set('file', $file)
        ->call('confirm')
        ->assertHasNoErrors();

    expect($component->get('lastImportSummary'))
        ->toMatchArray([
            'created' => 2,
            'duplicates' => 1,
            'invalid' => 1,
            'team_name' => 'Infrastructure',
        ]);

    // Preview state cleared so the user can upload another sheet
    expect($component->get('previewed'))->toBeFalse()
        ->and($component->get('validRows'))->toBeEmpty()
        ->and($component->get('invalidRows'))->toBeEmpty()
        ->and($component->get('duplicateRows'))->toBeEmpty();
});

it('flags when the chosen team is not one the user belongs to', function () {
    $alice = User::factory()->create(['is_staff' => true]);
    $aliceTeam = Team::factory()->create();
    $strangerTeam = Team::factory()->create();
    $aliceTeam->users()->attach($alice);

    $component = Livewire::actingAs($alice)
        ->test(ImportServers::class);

    expect($component->set('team_id', $aliceTeam->id)->get('chosenTeamIsForeign'))->toBeFalse();
    expect($component->set('team_id', $strangerTeam->id)->get('chosenTeamIsForeign'))->toBeTrue();
});

it('refuses non-staff users with a 403', function () {
    $student = User::factory()->create(['is_staff' => false]);

    $this->actingAs($student)
        ->get(route('import'))
        ->assertStatus(403);
});

it('queues a netbox sync when a staff user refreshes', function () {
    Bus::fake();
    $alice = User::factory()->create(['is_staff' => true]);

    Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->call('refreshFromNetbox');

    Bus::assertDispatched(SyncNetboxServers::class);
});

it('logs the user who triggers a netbox sync', function () {
    Bus::fake([SyncNetboxServers::class]);
    $alice = User::factory()->create(['is_staff' => true]);

    Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->call('refreshFromNetbox');

    $log = ActivityLog::sole();
    expect($log->user_id)->toBe($alice->id);
    expect($log->description)->toBe('Started a NetBox sync');
});

it('does not let a non-staff user reach the refresh action', function () {
    Bus::fake();
    $student = User::factory()->create(['is_staff' => false]);

    $this->actingAs($student)
        ->get(route('import'))
        ->assertForbidden();

    Bus::assertNotDispatched(SyncNetboxServers::class);
});

it('shows the last netbox sync summary when one is cached', function () {
    $alice = User::factory()->create(['is_staff' => true]);

    Cache::put('netbox.last_sync_summary', [
        'created' => 3,
        'updated' => 1,
        'reactivated' => 0,
        'inactive' => 2,
        'conflicts' => ['clash.example.com'],
        'ran_at' => now()->toIso8601String(),
    ]);

    Livewire::actingAs($alice)
        ->test(ImportServers::class)
        ->assertSee('Last NetBox sync')
        ->assertSee('clash.example.com');
});
