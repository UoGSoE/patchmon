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

    public function delete(int $id): void
    {
        Team::findOrFail($id)->delete();

        Flux::toast('Team deleted.', variant: 'success');
    }

    public function render()
    {
        return view('livewire.admin.teams', [
            'teams' => Team::orderBy('name')->get(),
        ]);
    }
}
