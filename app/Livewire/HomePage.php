<?php

namespace App\Livewire;

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Livewire\Forms\ServerForm;
use App\Models\Server;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class HomePage extends Component
{
    use WithPagination;

    private const PAGE_NAMES = ['teamPage', 'allPage', 'alertingPage', 'silencedPage'];

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

    public function mount(): void
    {
        if (request()->query('new')) {
            $this->openCreate();
        }
    }

    public function updatingFilter(): void
    {
        $this->resetAllPages();
    }

    public function updatingOsFilter(): void
    {
        $this->resetAllPages();
    }

    public function updatingTeamFilter(): void
    {
        $this->resetAllPages();
    }

    public function updatingSilencedFilter(): void
    {
        $this->resetAllPages();
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
}
