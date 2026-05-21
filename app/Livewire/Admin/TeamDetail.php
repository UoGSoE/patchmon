<?php

namespace App\Livewire\Admin;

use App\Models\Team;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class TeamDetail extends Component
{
    public Team $team;

    public ?int $userToAddId = null;

    public ?int $removingMemberId = null;

    public ?string $removingMemberName = null;

    public function mount(Team $team): void
    {
        $this->team = $team;
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
