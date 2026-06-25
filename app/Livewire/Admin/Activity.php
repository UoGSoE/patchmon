<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Activity extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public $search = '';

    #[Url(as: 'user')]
    public $userId = '';

    #[Url(as: 'server')]
    public $serverId = '';

    /** @var array<string, string>|string */
    public $dateRange = '';

    public $userSearch = '';

    public $serverSearch = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingUserId(): void
    {
        $this->resetPage();
    }

    public function updatingServerId(): void
    {
        $this->resetPage();
    }

    public function updatedDateRange(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return $this->applyFilters(ActivityLog::query())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        return User::query()
            ->when($this->userSearch !== '', fn ($query) => $query
                ->whereLike('forenames', '%'.$this->userSearch.'%')
                ->orWhereLike('surname', '%'.$this->userSearch.'%'))
            ->orderBy('surname')
            ->orderBy('forenames')
            ->limit(20)
            ->get();
    }

    /**
     * @return Collection<int, Server>
     */
    #[Computed]
    public function servers(): Collection
    {
        return Server::query()
            ->when($this->serverSearch !== '', fn ($query) => $query->whereLike('name', '%'.$this->serverSearch.'%'))
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.activity');
    }

    private function applyFilters(Builder $query): Builder
    {
        if ($this->userId !== '') {
            $query->where('user_id', $this->userId);
        }

        if ($this->serverId !== '') {
            $query->where('server_id', $this->serverId);
        }

        [$from, $to] = $this->dateBounds();

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $needle = trim((string) $this->search);

        if (strlen($needle) < 2) {
            return $query;
        }

        $pattern = '%'.$needle.'%';
        $query->where(fn ($outer) => $outer
            ->whereLike('user_name', $pattern)
            ->orWhereLike('server_name', $pattern));

        return $query;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function dateBounds(): array
    {
        if (! is_array($this->dateRange)) {
            return [null, null];
        }

        $start = $this->dateRange['start'] ?? null;
        $end = $this->dateRange['end'] ?? null;

        return [
            $start ? $start.' 00:00:00' : null,
            $end ? $end.' 23:59:59' : null,
        ];
    }
}
