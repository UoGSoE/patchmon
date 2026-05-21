<?php

namespace App\Livewire\Admin;

use App\Models\Team;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Teams extends Component
{
    public array $editing = [
        'id' => null,
        'name' => '',
        'notification_email' => '',
        'sender_email' => '',
    ];

    public ?int $deletingId = null;

    public ?int $transferTargetTeamId = null;

    public string $typedConfirmation = '';

    public function openCreate(): void
    {
        $this->reset('editing');
        Flux::modal('edit-team')->show();
    }

    public function openEdit(int $id): void
    {
        $team = Team::findOrFail($id);
        $this->editing = [
            'id' => $team->id,
            'name' => $team->name,
            'notification_email' => $team->notification_email,
            'sender_email' => $team->sender_email,
        ];
        Flux::modal('edit-team')->show();
    }

    public function save(): void
    {
        $this->validate([
            'editing.name' => ['required', 'string', 'max:255'],
            'editing.notification_email' => ['required', 'email'],
            'editing.sender_email' => ['nullable', 'email'],
        ]);

        $team = Team::findOrNew($this->editing['id']);
        $team->fill([
            'name' => $this->editing['name'],
            'notification_email' => $this->editing['notification_email'],
            'sender_email' => $this->editing['sender_email'] ?: null,
        ])->save();

        Flux::modal('edit-team')->close();
        Flux::toast('Team saved.', variant: 'success');
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->transferTargetTeamId = null;
        $this->typedConfirmation = '';
        Flux::modal('delete-team')->show();
    }

    public function deleteEmpty(): void
    {
        $team = Team::findOrFail($this->deletingId);
        $team->delete();

        Flux::modal('delete-team')->close();
        Flux::toast('Team deleted.', variant: 'success');
    }

    public function transferAndDelete(): void
    {
        $this->validate([
            'deletingId' => ['required', 'exists:teams,id'],
            'transferTargetTeamId' => ['required', 'different:deletingId', 'exists:teams,id'],
        ]);

        $team = Team::findOrFail($this->deletingId);
        $team->servers()->update(['team_id' => $this->transferTargetTeamId]);
        $team->delete();

        Flux::modal('delete-team')->close();
        Flux::toast('Team deleted; servers transferred.', variant: 'success');
    }

    public function deleteWithServers(): void
    {
        $team = Team::findOrFail($this->deletingId);

        if ($this->typedConfirmation !== $team->name) {
            return;
        }

        $team->delete();

        Flux::modal('delete-team')->close();
        Flux::toast('Team and its servers deleted.', variant: 'success');
    }

    public function render()
    {
        $deletingTeam = $this->deletingId
            ? Team::with('servers')->find($this->deletingId)
            : null;

        return view('livewire.admin.teams', [
            'teams' => Team::orderBy('name')->get(),
            'deletingTeam' => $deletingTeam,
            'otherTeams' => $deletingTeam
                ? Team::where('id', '!=', $deletingTeam->id)->orderBy('name')->get()
                : collect(),
        ]);
    }
}
