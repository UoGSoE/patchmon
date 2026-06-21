<?php

use App\Enums\OsType;
use App\Livewire\HomePage;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;
use Ohffs\SimpleSpout\ExcelSheet;

/**
 * Decode the file a Livewire download action produced, write it to a temp
 * path, and read back the rows of the requested (0-indexed) sheet.
 *
 * @return array<int, array<int, mixed>>
 */
function readDownloadedSheet($component, int $sheetIndex): array
{
    $content = base64_decode(data_get($component->effects, 'download.content'));
    $path = tempnam(sys_get_temp_dir(), 'export-test');
    file_put_contents($path, $content);

    return (new ExcelSheet)->importSheet($path, $sheetIndex);
}

it('exports each visible tab as its own sheet with the current filters applied', function () {
    $user = User::factory()->create();
    $myTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user->teams()->attach($myTeam);

    Server::factory()->forTeam($myTeam)->create([
        'name' => 'web-linux.example.test',
        'os_type' => OsType::Linux,
    ]);
    Server::factory()->forTeam($myTeam)->create([
        'name' => 'win-box.example.test',
        'os_type' => OsType::Windows,
    ]);
    Server::factory()->forTeam($otherTeam)->create([
        'name' => 'stranger-linux.example.test',
        'os_type' => OsType::Linux,
    ]);

    $component = Livewire::actingAs($user)
        ->test(HomePage::class)
        ->set('osFilter', OsType::Linux->value)
        ->call('export')
        ->assertFileDownloaded('patchmon-servers-'.now()->format('Y-m-d').'.xlsx');

    // Sheet 0 is "Team servers" — only my team's Linux box survives the filter.
    $teamNames = collect(readDownloadedSheet($component, 0))->skip(1)->pluck(0);
    expect($teamNames)->toContain('web-linux.example.test')
        ->not->toContain('win-box.example.test')
        ->not->toContain('stranger-linux.example.test');

    // Sheet 1 is "All servers" — every team's Linux box, still excluding Windows.
    $allNames = collect(readDownloadedSheet($component, 1))->skip(1)->pluck(0);
    expect($allNames)->toContain('web-linux.example.test')
        ->toContain('stranger-linux.example.test')
        ->not->toContain('win-box.example.test');

    // The header row carries the richer columns.
    expect(readDownloadedSheet($component, 0)[0])->toBe([
        'Name', 'Description', 'Location', 'OS', 'Team', 'Schedule', 'Grace',
        'Created', 'Last patched', 'Next due', 'Status', 'Alerting since', 'Silenced until', 'Silence reason',
    ]);
});

it('adds a Created column carrying the server creation date to the export', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team);

    $server = Server::factory()->forTeam($team)->create([
        'name' => 'created-col.example.test',
        'created_at' => now()->subDays(10),
    ]);

    $component = Livewire::actingAs($user)
        ->test(HomePage::class)
        ->call('export');

    $rows = readDownloadedSheet($component, 0);

    expect($rows[0][7])->toBe('Created')
        ->and($rows[1][7])->toBe($server->created_at->format('Y-m-d H:i'));
});

it('exports every matching server, ignoring the per-page limit', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team);

    Server::factory()->forTeam($team)->count(30)->create();

    $component = Livewire::actingAs($user)
        ->test(HomePage::class)
        ->set('perPage', '25')
        ->call('export');

    // All 30 rows survive even though a page only holds 25 (skip the header row).
    expect(collect(readDownloadedSheet($component, 1))->skip(1))->toHaveCount(30);
});

it('includes the Unassigned servers sheet', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team);

    $component = Livewire::actingAs($user)
        ->test(HomePage::class)
        ->call('export');

    // The Unassigned servers sheet (index 4) is always present, header row and all.
    expect(readDownloadedSheet($component, 4))->not->toBeEmpty();
});

it('includes a Never checked in sheet listing never-patched servers with a blank Last patched', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team);

    Server::factory()->forTeam($team)->create(['name' => 'never-here.example.test', 'last_patched_at' => null]);
    Server::factory()->forTeam($team)->create(['name' => 'patched-here.example.test', 'last_patched_at' => now()->subDays(2)]);

    $component = Livewire::actingAs($user)
        ->test(HomePage::class)
        ->call('export');

    // Sheet 5 is "Never checked in" (after Team/All/Alerting/Silenced/Unassigned).
    $rows = readDownloadedSheet($component, 5);
    $names = collect($rows)->skip(1)->pluck(0);

    expect($names)->toContain('never-here.example.test')
        ->not->toContain('patched-here.example.test');

    // The motivating detail: Created (index 7) is populated, Last patched (index 8) is blank.
    $neverRow = collect($rows)->firstWhere(0, 'never-here.example.test');
    expect($neverRow[7])->not->toBe('')
        ->and($neverRow[8])->toBe('');
});

it('offers an export button on the home page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(HomePage::class)
        ->assertSeeHtml('wire:click="export"');
});
