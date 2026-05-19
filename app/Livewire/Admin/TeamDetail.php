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

    public ?int $removingMemberId = null;

    public ?string $removingMemberName = null;

    public bool $silenced = false;

    public string $silenceUntil = '';

    public ?string $silenceReason = null;

    public function mount(Team $team): void
    {
        $this->team = $team;
        $this->silenced = $team->isCurrentlySilenced();
        $this->silenceUntil = $team->silenced_until
            ? $team->silenced_until->format('Y-m-d\TH:i')
            : now()->addDay()->format('Y-m-d\TH:i');
        $this->silenceReason = $team->silence_reason;
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

    public function confirmRemoveUser(int $userId): void
    {
        $member = $this->team->users()->whereKey($userId)->firstOrFail();

        $this->removingMemberId = $member->id;
        $this->removingMemberName = $member->full_name ?: $member->email;

        Flux::modal('remove-member')->show();
    }

    public function removeUser(int $userId): void
    {
        $this->team->users()->detach($userId);

        $this->removingMemberId = null;
        $this->removingMemberName = null;

        Flux::modal('remove-member')->close();
        Flux::toast('User removed from team.', variant: 'success');
    }

    public function updatedSilenced(bool $value): void
    {
        if ($value) {
            $this->validate(['silenceUntil' => ['required', 'date', 'after:now']]);
            $this->team->silenceUntil(Carbon::parse($this->silenceUntil), $this->silenceReason);
            Flux::toast('Team silenced.', variant: 'success');
        } else {
            $this->team->unsilence();
            $this->silenceReason = null;
            $this->silenceUntil = now()->addDay()->format('Y-m-d\TH:i');
            Flux::toast('Team unsilenced.', variant: 'success');
        }
    }

    public function updatedSilenceUntil(): void
    {
        if (! $this->silenced) {
            return;
        }
        $this->validate(['silenceUntil' => ['required', 'date', 'after:now']]);
        $this->team->silenceUntil(Carbon::parse($this->silenceUntil), $this->silenceReason);
    }

    public function updatedSilenceReason(): void
    {
        if (! $this->silenced) {
            return;
        }
        $this->validate(['silenceReason' => ['nullable', 'string', 'max:255']]);
        $this->team->silenceUntil(Carbon::parse($this->silenceUntil), $this->silenceReason);
    }

    public function render()
    {
        return view('livewire.admin.team-detail', [
            'team' => $this->team->fresh(),
            'members' => $this->team->users()->orderBy('surname')->orderBy('forenames')->get(),
            'candidates' => User::query()
                ->whereDoesntHave('teams', fn ($q) => $q->whereKey($this->team->id))
                ->orderBy('surname')
                ->orderBy('forenames')
                ->get(),
        ]);
    }
}
