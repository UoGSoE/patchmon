<?php

namespace App\Livewire;

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Livewire\Forms\ServerForm;
use App\Models\Server;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class HomePage extends Component
{
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

    public ServerForm $form;

    public function mount(): void
    {
        if (request()->query('new')) {
            $this->openCreate();
        }
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
    public function teamServers(): Collection
    {
        $teamIds = auth()->user()->teams()->pluck('teams.id');

        return $this->sortForListing(
            $this->applyFilter(
                Server::query()
                    ->whereIn('team_id', $teamIds)
                    ->with(['team'])
            )->get()
        );
    }

    #[Computed]
    public function allServers(): Collection
    {
        return $this->sortForListing(
            $this->applyFilter(
                Server::query()->with(['team'])
            )->get()
        );
    }

    #[Computed]
    public function alertingServers(): Collection
    {
        $user = auth()->user();

        $query = Server::query()
            ->whereNotNull('alerting_since')
            ->with(['team']);

        if (! $user->is_admin) {
            $teamIds = $user->teams()->pluck('teams.id');
            $query->whereIn('team_id', $teamIds);
        }

        return $this->sortForListing($this->applyFilter($query)->get());
    }

    #[Computed]
    public function silencedServers(): Collection
    {
        return $this->sortForListing(
            $this->applyFilter(
                Server::query()
                    ->where('silenced_from', '<=', now())
                    ->where('silenced_until', '>=', now())
                    ->with(['team'])
            )->get()
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

    private function sortForListing(Collection $servers): Collection
    {
        return $servers->sort(function (Server $a, Server $b) {
            $aAlerting = $a->alerting_since !== null;
            $bAlerting = $b->alerting_since !== null;

            if ($aAlerting !== $bAlerting) {
                return $aAlerting ? -1 : 1;
            }

            if ($aAlerting) {
                return $b->alerting_since->timestamp <=> $a->alerting_since->timestamp;
            }

            return strcasecmp($a->name, $b->name);
        })->values();
    }
}
