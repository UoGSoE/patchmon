<?php

namespace App\Livewire;

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use App\Livewire\Forms\ServerForm;
use App\Models\Server;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class HomePage extends Component
{
    #[Url(as: 'tab')]
    public $tab = 'mine';

    #[Url(as: 'q')]
    public $filter = '';

    #[Url(as: 'invert')]
    public $excludeFilter = false;

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
        $this->form->grace_units = GraceUnit::Minutes->value;
        $this->form->schedule_frequency = 1;
        $this->form->grace_value = 1;

        Flux::modal('server-form')->show();
    }

    public function save(): void
    {
        $this->form->save();

        Flux::modal('server-form')->close();
        Flux::toast('Server created.', variant: 'success');

        unset($this->myServers, $this->teamServers);
    }

    #[Computed]
    public function myServers(): Collection
    {
        return $this->sortForListing(
            $this->applyFilter(
                Server::query()
                    ->where('user_id', auth()->id())
                    ->with(['team', 'user'])
            )->get()
        );
    }

    #[Computed]
    public function teamServers(): Collection
    {
        $teamIds = auth()->user()->teams()->pluck('teams.id');

        return $this->sortForListing(
            $this->applyFilter(
                Server::query()
                    ->whereIn('team_id', $teamIds)
                    ->with(['team', 'user'])
            )->get()
        );
    }

    #[Computed]
    public function alertingServers(): Collection
    {
        $user = auth()->user();

        $query = Server::query()
            ->whereNotNull('alerting_since')
            ->with(['team', 'user']);

        if (! $user->is_admin) {
            $teamIds = $user->teams()->pluck('teams.id');

            $query->where(function ($builder) use ($user, $teamIds) {
                $builder->where('user_id', $user->id)
                    ->orWhereIn('team_id', $teamIds);
            });
        }

        return $this->sortForListing($this->applyFilter($query)->get());
    }

    #[Computed]
    public function userIsInAnyTeam(): bool
    {
        return auth()->user()->teams()->exists();
    }

    public function render()
    {
        return view('livewire.home-page', [
            'teams' => auth()->user()->teams()->orderBy('name')->get(),
            'intervalOptions' => ScheduleInterval::cases(),
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
        $needle = trim((string) $this->filter);

        if (strlen($needle) < 2) {
            return $query;
        }

        $tokens = preg_split('/\s+/', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $columns = ['name', 'description', 'location'];

        if ($this->excludeFilter) {
            foreach ($tokens as $token) {
                $pattern = '%'.$token.'%';
                $query->where(function ($outer) use ($pattern, $columns) {
                    foreach ($columns as $column) {
                        $outer->where(fn ($inner) => $inner->whereNull($column)->orWhereNotLike($column, $pattern));
                    }
                });
            }

            return $query;
        }

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
