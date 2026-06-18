<?php

namespace App\Livewire;

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Jobs\SyncNetboxServers;
use App\Models\Server;
use App\Models\Team;
use App\Rules\Fqdn;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Ohffs\SimpleSpout\ExcelSheet;

class ImportServers extends Component
{
    use WithFileUploads;

    public ?int $team_id = null;

    public int $interval_months = 1;

    public int $grace_value = 7;

    public string $grace_units = '';

    public $file = null;

    public bool $firstRowIsHeader = true;

    public bool $previewed = false;

    public array $validRows = [];

    public array $invalidRows = [];

    public array $duplicateRows = [];

    public ?array $lastImportSummary = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->is_staff, 403);

        $this->grace_units = GraceUnit::Days->value;
    }

    public function updatedFirstRowIsHeader(): void
    {
        if ($this->file !== null) {
            $this->updatedFile();
        }
    }

    public function updatedFile(): void
    {
        $this->validRows = [];
        $this->invalidRows = [];
        $this->duplicateRows = [];
        $this->previewed = true;

        $rows = (new ExcelSheet)->trimmedImport($this->file->getRealPath());
        $rowOffset = 1;

        if ($this->firstRowIsHeader) {
            array_shift($rows);
            $rowOffset = 2;
        }

        $existingNames = Server::query()->pluck('name')->all();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + $rowOffset;
            $name = strtolower((string) ($row[0] ?? ''));
            $os = strtolower((string) ($row[1] ?? ''));
            $lastPatched = $row[2] ?? null;

            $reason = $this->validateRow($name, $os, $lastPatched);

            if ($reason !== null) {
                $this->invalidRows[] = ['row' => $rowNumber, 'name' => $name, 'reason' => $reason];

                continue;
            }

            if (in_array($name, $existingNames, true)) {
                $this->duplicateRows[] = ['row' => $rowNumber, 'name' => $name];

                continue;
            }

            $this->validRows[] = [
                'row' => $rowNumber,
                'name' => $name,
                'os_type' => $os,
                'last_patched_at' => $lastPatched,
            ];
        }
    }

    private function validateRow(string $name, string $os, mixed $lastPatched): ?string
    {
        $validator = Validator::make(
            ['name' => $name],
            ['name' => ['required', 'string', 'max:255', new Fqdn]],
        );

        if ($validator->fails()) {
            return 'Server name must be a valid FQDN.';
        }

        if (OsType::tryFrom($os) === null) {
            return 'OS must be one of: '.implode(', ', array_map(fn ($c) => $c->value, OsType::cases())).'.';
        }

        $parsed = $this->parseLastPatched($lastPatched);

        if ($parsed === false) {
            return 'Last patched date is not a recognisable date.';
        }

        if ($parsed instanceof Carbon && $parsed->isFuture()) {
            return 'Last patched date cannot be in the future.';
        }

        return null;
    }

    private function parseLastPatched(mixed $value): Carbon|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        $string = (string) $value;
        $validator = Validator::make(['date' => $string], ['date' => ['date']]);

        if ($validator->fails()) {
            return false;
        }

        return Carbon::parse($string);
    }

    public function confirm(): void
    {
        $this->validate([
            'team_id' => ['required', Rule::exists('teams', 'id')],
            'interval_months' => ['required', 'integer', 'min:1'],
            'grace_value' => ['required', 'integer', 'min:1'],
            'grace_units' => ['required', Rule::enum(GraceUnit::class)],
        ]);

        $createdCount = count($this->validRows);
        $duplicateCount = count($this->duplicateRows);
        $invalidCount = count($this->invalidRows);
        $teamName = Team::findOrFail($this->team_id)->name;

        DB::transaction(function () {
            foreach ($this->validRows as $row) {
                $server = Server::create([
                    'team_id' => $this->team_id,
                    'created_by_user_id' => auth()->id(),
                    'name' => $row['name'],
                    'os_type' => $row['os_type'],
                    'interval_months' => $this->interval_months,
                    'grace_value' => $this->grace_value,
                    'grace_units' => $this->grace_units,
                ]);

                $patchedAt = $this->parseLastPatched($row['last_patched_at']);

                if ($patchedAt instanceof Carbon) {
                    $server->recordPatch(null, 'Imported from spreadsheet', null, $patchedAt);
                }
            }
        });

        $this->lastImportSummary = [
            'created' => $createdCount,
            'duplicates' => $duplicateCount,
            'invalid' => $invalidCount,
            'team_name' => $teamName,
        ];

        $this->file = null;
        $this->previewed = false;
        $this->validRows = [];
        $this->invalidRows = [];
        $this->duplicateRows = [];
    }

    public function refreshFromNetbox(): void
    {
        SyncNetboxServers::dispatch();

        Flux::toast('Sync queued.', variant: 'success');
    }

    #[Computed]
    public function lastNetboxSync(): ?array
    {
        return Cache::get('netbox.last_sync_summary');
    }

    #[Computed]
    public function chosenTeamIsForeign(): bool
    {
        if ($this->team_id === null) {
            return false;
        }

        return ! auth()->user()->teams()->where('teams.id', $this->team_id)->exists();
    }

    public function render()
    {
        return view('livewire.import-servers', [
            'teams' => Team::query()->orderBy('name')->get(),
            'graceUnitOptions' => GraceUnit::cases(),
        ]);
    }
}
