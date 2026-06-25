<?php

namespace App\Livewire;

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Events\ActivityOccurred;
use App\Livewire\Forms\ServerForm;
use App\Models\Server;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class HomePage extends Component
{
    use WithPagination;

    private const PAGE_NAMES = ['teamPage', 'allPage', 'alertingPage', 'silencedPage', 'unassignedPage', 'neverCheckedInPage'];

    #[Url(as: 'tab')]
    public $tab = 'teams';

    #[Url(as: 'q')]
    public $filter = '';

    #[Url(as: 'os')]
    public $osFilter = '';

    #[Url(as: 'team')]
    public $teamFilter = '';

    #[Url(as: 'silenced')]
    public $silencedFilter = '';

    #[Url(as: 'per')]
    public $perPage = '50';

    public ServerForm $form;

    /** @var array<int, int> */
    public array $selected = [];

    public bool $selectAllMatching = false;

    public ?int $allocateTeamId = null;

    public int $allocateIntervalMonths = 1;

    public int $allocateGraceValue = 7;

    public string $allocateGraceUnits = '';

    public function mount(): void
    {
        if (request()->query('new')) {
            $this->openCreate();
        }
    }

    public function updatingFilter(): void
    {
        $this->resetAllPages();
        $this->resetSelection();
    }

    public function updatingOsFilter(): void
    {
        $this->resetAllPages();
        $this->resetSelection();
    }

    public function updatingTeamFilter(): void
    {
        $this->resetAllPages();
        $this->resetSelection();
    }

    public function updatingSilencedFilter(): void
    {
        $this->resetAllPages();
        $this->resetSelection();
    }

    public function updatedSelectAllMatching(bool $value): void
    {
        // Fill the selection with every matching unassigned id (across all pages) so
        // the row checkboxes reflect it; clear it when unticked.
        $this->selected = $value ? $this->matchingUnassignedIds()->all() : [];
    }

    public function updatingPerPage(): void
    {
        $this->resetAllPages();
    }

    public function openCreate(): void
    {
        $this->form->reset();
        $this->form->resetErrorBag();
        $this->form->os_type = OsType::Linux->value;
        $this->form->grace_units = GraceUnit::Days->value;
        $this->form->interval_months = 1;
        $this->form->grace_value = 7;

        Flux::modal('server-form')->show();
    }

    public function save(): void
    {
        $this->form->save();

        Flux::modal('server-form')->close();
        Flux::toast('Server created.', variant: 'success');

        unset($this->teamServers, $this->alertingServers, $this->silencedServers);
    }

    public function openAllocate(): void
    {
        $this->resetErrorBag();
        $this->allocateTeamId = null;
        $this->allocateIntervalMonths = 1;
        $this->allocateGraceValue = 7;
        $this->allocateGraceUnits = GraceUnit::Days->value;

        Flux::modal('bulk-allocate')->show();
    }

    public function bulkAllocate(): void
    {
        $this->validate([
            'allocateTeamId' => ['required', Rule::exists('teams', 'id')],
            'allocateIntervalMonths' => ['required', 'integer', 'min:1'],
            'allocateGraceValue' => ['required', 'integer', 'min:1'],
            'allocateGraceUnits' => ['required', Rule::enum(GraceUnit::class)],
        ]);

        // $selected is the single source of truth — "select all matching" fills it
        // (see updatedSelectAllMatching). The whereNull guard is belt-and-braces so a
        // stale id for an already-allocated server can never be re-homed.
        $targetIds = Server::query()
            ->whereNull('team_id')
            ->whereIn('id', $this->selected)
            ->pluck('id');

        if ($targetIds->isEmpty()) {
            Flux::toast('No servers selected.', variant: 'warning');

            return;
        }

        Server::query()->whereIn('id', $targetIds)->update([
            'team_id' => $this->allocateTeamId,
            'interval_months' => $this->allocateIntervalMonths,
            'grace_value' => $this->allocateGraceValue,
            'grace_units' => $this->allocateGraceUnits,
        ]);

        Server::query()->whereIn('id', $targetIds)
            ->whereNull('created_by_user_id')
            ->update(['created_by_user_id' => auth()->id()]);

        $team = Team::findOrFail($this->allocateTeamId);

        foreach ($targetIds as $serverId) {
            ActivityOccurred::dispatch(auth()->id(), $serverId, "Allocated the server to {$team->name}", request()->ip());
        }

        $count = $targetIds->count();

        $this->reset('selected', 'selectAllMatching');
        Flux::modal('bulk-allocate')->close();
        unset($this->unassignedServers, $this->teamServers, $this->allServers);
        Flux::toast($count.' '.str('server')->plural($count).' allocated.', variant: 'success');
    }

    public function export()
    {
        $path = $this->writeWorkbook($this->exportSheets());

        return response()->download($path, 'patchmon-servers-'.now()->format('Y-m-d').'.xlsx')
            ->deleteFileAfterSend();
    }

    #[Computed]
    public function teamServers(): LengthAwarePaginator
    {
        $teamIds = auth()->user()->teams()->pluck('teams.id');

        return $this->applySortAndPaginate(
            $this->applyFilter(
                Server::query()
                    ->whereIn('team_id', $teamIds)
                    ->with(['team'])
            ),
            'teamPage'
        );
    }

    #[Computed]
    public function allServers(): LengthAwarePaginator
    {
        return $this->applySortAndPaginate(
            $this->applyFilter(
                Server::query()->with(['team'])
            ),
            'allPage'
        );
    }

    #[Computed]
    public function alertingServers(): LengthAwarePaginator
    {
        $user = auth()->user();

        $query = Server::query()
            ->whereNotNull('alerting_since')
            ->with(['team']);

        if (! $user->is_admin) {
            $teamIds = $user->teams()->pluck('teams.id');
            $query->whereIn('team_id', $teamIds);
        }

        return $this->applySortAndPaginate($this->applyFilter($query), 'alertingPage');
    }

    #[Computed]
    public function silencedServers(): LengthAwarePaginator
    {
        return $this->applySortAndPaginate(
            $this->applyFilter(
                Server::query()
                    ->where('silenced_from', '<=', now())
                    ->where('silenced_until', '>=', now())
                    ->with(['team'])
            ),
            'silencedPage'
        );
    }

    #[Computed]
    public function unassignedServers(): LengthAwarePaginator
    {
        return $this->applySortAndPaginate(
            $this->applyFilter(
                Server::query()->whereNull('team_id')
            ),
            'unassignedPage'
        );
    }

    #[Computed]
    public function neverCheckedInServers(): LengthAwarePaginator
    {
        return $this->applySortAndPaginate(
            $this->applyFilter(
                Server::neverCheckedIn()->with('team')
            ),
            'neverCheckedInPage'
        );
    }

    #[Computed]
    public function selectedCount(): int
    {
        return count($this->selected);
    }

    #[Computed]
    public function userIsInAnyTeam(): bool
    {
        return auth()->user()->teams()->exists();
    }

    public function render()
    {
        return view('livewire.home-page', [
            'userTeams' => auth()->user()->teams()->orderBy('name')->get(),
            'allTeams' => Team::query()->orderBy('name')->get(),
            'osTypeOptions' => OsType::cases(),
            'graceUnitOptions' => GraceUnit::cases(),
            'existingLocations' => Server::query()
                ->whereNotNull('location')
                ->distinct()
                ->orderBy('location')
                ->pluck('location'),
        ]);
    }

    private function resetAllPages(): void
    {
        foreach (self::PAGE_NAMES as $pageName) {
            $this->resetPage(pageName: $pageName);
        }
    }

    private function resetSelection(): void
    {
        $this->reset('selected', 'selectAllMatching');
    }

    /**
     * @return Collection<int, int>
     */
    private function matchingUnassignedIds(): Collection
    {
        return $this->applyFilter(Server::query()->whereNull('team_id'))->pluck('id');
    }

    private function applyFilter(Builder $query): Builder
    {
        if ($this->osFilter !== '') {
            $query->where('os_type', $this->osFilter);
        }

        if ($this->teamFilter !== '') {
            $query->where('team_id', $this->teamFilter);
        }

        if ($this->silencedFilter === 'silenced') {
            $query->where('silenced_from', '<=', now())
                ->where('silenced_until', '>=', now());
        }

        if ($this->silencedFilter === 'active') {
            $query->where(fn ($q) => $q->whereNull('silenced_until')
                ->orWhere('silenced_until', '<', now())
                ->orWhere('silenced_from', '>', now()));
        }

        $needle = trim((string) $this->filter);

        if (strlen($needle) < 2) {
            return $query;
        }

        $tokens = preg_split('/\s+/', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $columns = ['name', 'description', 'location'];

        foreach ($tokens as $token) {
            $pattern = '%'.$token.'%';
            $query->where(function ($outer) use ($pattern, $columns) {
                foreach ($columns as $i => $column) {
                    $i === 0
                        ? $outer->whereLike($column, $pattern)
                        : $outer->orWhereLike($column, $pattern);
                }
            });
        }

        return $query;
    }

    private function applySortAndPaginate(Builder $query, string $pageName): LengthAwarePaginator
    {
        // (alerting_since IS NULL) sorts non-null first on both MySQL and Postgres,
        // sidestepping each engine's default null ordering.
        return $query
            ->orderByRaw('alerting_since IS NULL')
            ->orderByDesc('alerting_since')
            ->orderBy('name')
            ->paginate($this->effectivePerPage(), ['*'], $pageName);
    }

    private function effectivePerPage(): int
    {
        return $this->perPage === 'all' ? 10000 : (int) $this->perPage;
    }

    /**
     * One entry per visible tab, keyed by the sheet name. The base queries
     * mirror the tab computed properties above so the export matches what the
     * user is looking at; the current filters apply, but pagination does not.
     *
     * @return array<string, Collection<int, Server>>
     */
    private function exportSheets(): array
    {
        $user = auth()->user();
        $teamIds = $user->teams()->pluck('teams.id');

        $alerting = Server::query()->whereNotNull('alerting_since');

        if (! $user->is_admin) {
            $alerting->whereIn('team_id', $teamIds);
        }

        return [
            'Team servers' => $this->collectForExport(Server::query()->whereIn('team_id', $teamIds)),
            'All servers' => $this->collectForExport(Server::query()),
            'Alerting servers' => $this->collectForExport($alerting),
            'Silenced servers' => $this->collectForExport(
                Server::query()->where('silenced_from', '<=', now())->where('silenced_until', '>=', now())
            ),
            'Unassigned servers' => $this->collectForExport(Server::query()->whereNull('team_id')),
            'Never checked in' => $this->collectForExport(Server::neverCheckedIn()),
        ];
    }

    /**
     * @return Collection<int, Server>
     */
    private function collectForExport(Builder $query): Collection
    {
        return $this->applyFilter($query->with('team'))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, Collection<int, Server>>  $sheets
     */
    private function writeWorkbook(array $sheets): string
    {
        // simple-spout only writes a single sheet, so we drive OpenSpout directly
        // here to get one sheet per tab.
        $path = tempnam(sys_get_temp_dir(), 'patchmon-export');

        $writer = new Writer;
        $writer->openToFile($path);

        $first = true;

        foreach ($sheets as $name => $servers) {
            $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($name);
            $first = false;

            $writer->addRow(Row::fromValues($this->exportHeader()));

            foreach ($servers as $server) {
                $writer->addRow(Row::fromValues($this->serverToRow($server)));
            }
        }

        $writer->close();

        return $path;
    }

    /**
     * @return array<int, string>
     */
    private function exportHeader(): array
    {
        return ['Name', 'Description', 'Location', 'OS', 'Team', 'Schedule', 'Grace',
            'Created', 'Last patched', 'Next due', 'Status', 'Alerting since', 'Silenced until', 'Silence reason'];
    }

    /**
     * @return array<int, string>
     */
    private function serverToRow(Server $server): array
    {
        return [
            $server->name,
            $server->description ?? '',
            $server->location ?? '',
            $server->os_type->label(),
            $server->team?->name ?? 'Unassigned',
            $server->intervalLabel(),
            $server->grace_value.' '.strtolower($server->grace_units->label()),
            $server->created_at->format('Y-m-d H:i'),
            $server->last_patched_at?->format('Y-m-d H:i') ?? '',
            $server->deadline()->format('Y-m-d H:i'),
            $this->exportStatus($server),
            $server->alerting_since?->format('Y-m-d H:i') ?? '',
            $server->silenced_until?->format('Y-m-d H:i') ?? '',
            $server->silence_reason ?? '',
        ];
    }

    private function exportStatus(Server $server): string
    {
        return match (true) {
            $server->isInactive() => 'Inactive',
            $server->alerting_since !== null => 'Overdue',
            default => 'OK',
        };
    }
}
