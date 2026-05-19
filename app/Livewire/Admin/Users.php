<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Users extends Component
{
    public function toggleAdmin(int $id): void
    {
        $user = User::findOrFail($id);
        $user->update(['is_admin' => ! $user->is_admin]);

        Flux::toast(
            $user->is_admin ? "{$user->email} is now an admin." : "{$user->email} is no longer an admin.",
            variant: 'success',
        );
    }

    public function toggleStaff(int $id): void
    {
        $user = User::findOrFail($id);
        $user->update(['is_staff' => ! $user->is_staff]);

        Flux::toast('Staff flag updated.', variant: 'success');
    }

    public function render()
    {
        return view('livewire.admin.users', [
            'users' => User::orderBy('surname')->orderBy('forenames')->get(),
        ]);
    }
}
