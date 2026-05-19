<?php

namespace App\Livewire\Admin;

use App\Models\Team;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class TeamDetail extends Component
{
    public Team $team;

    public ?int $userToAddId = null;

    public string $silenceUntil = '';

    public ?string $silenceReason = null;

    public function mount(Team $team): void
    {
        $this->team = $team;
        $this->silenceUntil = now()->addDay()->format('Y-m-d\TH:i');
    }

    public function addUser(): void
    {
        $this->validate([
            'userToAddId' => ['required', 'integer', 'exists:users,id'],
        ]);

        $this->team->users()->syncWithoutDetaching($this->userToAddId);

        $this->userToAddId = null;
        Flux::toast('User added to team.', variant: 'success');
    }

    public function removeUser(int $userId): void
    {
        $this->team->users()->detach($userId);

        Flux::toast('User removed from team.', variant: 'success');
    }

    public function silence(): void
    {
        $this->validate([
            'silenceUntil' => ['required', 'date', 'after:now'],
            'silenceReason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->team->silenceUntil(Carbon::parse($this->silenceUntil), $this->silenceReason);

        Flux::modal('silence-team')->close();
        Flux::toast('Team silenced.', variant: 'success');
    }

    public function unsilence(): void
    {
        $this->team->unsilence();

        Flux::toast('Team unsilenced.', variant: 'success');
    }

    public function render()
    {
        return view('livewire.admin.team-detail', [
            'team' => $this->team->fresh(),
            'members' => $this->team->users()->orderBy('name')->get(),
            'candidates' => User::query()
                ->whereDoesntHave('teams', fn ($q) => $q->whereKey($this->team->id))
                ->orderBy('name')
                ->get(),
        ]);
    }
}
