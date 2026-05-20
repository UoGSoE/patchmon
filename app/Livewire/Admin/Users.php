<?php

namespace App\Livewire\Admin;

use App\Models\Job;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Users extends Component
{
    public ?int $editingUserId = null;

    public array $form = [
        'username' => '',
        'forenames' => '',
        'surname' => '',
        'email' => '',
    ];

    public ?int $deletingUserId = null;

    public ?string $deletingUserName = null;

    public int $deletingUserPersonalJobCount = 0;

    public ?int $transferTargetUserId = null;

    public string $typedConfirmation = '';

    public function toggleAdmin(int $id): void
    {
        if ($id === auth()->id()) {
            return;
        }

        $user = User::findOrFail($id);
        $user->update(['is_admin' => ! $user->is_admin]);

        Flux::toast(
            $user->is_admin ? "{$user->email} is now an admin." : "{$user->email} is no longer an admin.",
            variant: 'success',
        );
    }

    public function openCreate(): void
    {
        $this->editingUserId = null;
        $this->form = [
            'username' => '',
            'forenames' => '',
            'surname' => '',
            'email' => '',
        ];
        $this->resetErrorBag();

        Flux::modal('user-form')->show();
    }

    public function openEdit(int $id): void
    {
        $user = User::findOrFail($id);

        $this->editingUserId = $user->id;
        $this->form = [
            'username' => $user->username,
            'forenames' => $user->forenames,
            'surname' => $user->surname,
            'email' => $user->email,
        ];
        $this->resetErrorBag();

        Flux::modal('user-form')->show();
    }

    public function save(): void
    {
        $this->validate([
            'form.username' => [
                'required',
                'string',
                'regex:/^[a-z]+[0-9]+[a-z]$/',
                Rule::unique('users', 'username')->ignore($this->editingUserId),
            ],
            'form.forenames' => ['required', 'string', 'max:255'],
            'form.surname' => ['required', 'string', 'max:255'],
            'form.email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->editingUserId)],
        ]);

        if ($this->editingUserId) {
            $user = User::findOrFail($this->editingUserId);
            $user->update($this->form);
            $message = 'User updated.';
        } else {
            User::create([
                ...$this->form,
                'is_staff' => true,
                'is_admin' => false,
                'password' => bcrypt(Str::random(64)),
            ]);
            $message = 'User created.';
        }

        Flux::modal('user-form')->close();
        Flux::toast($message, variant: 'success');

        $this->editingUserId = null;
    }

    public function confirmDelete(int $id): void
    {
        if ($id === auth()->id()) {
            return;
        }

        $user = User::findOrFail($id);

        $this->deletingUserId = $user->id;
        $this->deletingUserName = $user->full_name ?: $user->email;
        $this->deletingUserPersonalJobCount = Job::where('user_id', $user->id)->count();
        $this->transferTargetUserId = null;
        $this->typedConfirmation = '';
        $this->resetErrorBag();

        Flux::modal('delete-user')->show();
    }

    public function transferAndDelete(): void
    {
        $this->validate([
            'transferTargetUserId' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::notIn([$this->deletingUserId]),
            ],
        ]);

        $user = User::findOrFail($this->deletingUserId);

        Job::where('user_id', $user->id)->update(['user_id' => $this->transferTargetUserId]);
        $this->reassignAuthorshipAndDelete($user, $this->transferTargetUserId);

        Flux::modal('delete-user')->close();
        Flux::toast('User deleted; personal jobs transferred.', variant: 'success');
        $this->resetDeleteState();
    }

    public function deleteWithJobs(): void
    {
        $user = User::findOrFail($this->deletingUserId);

        $this->validate([
            'typedConfirmation' => ['required', Rule::in([$user->full_name ?: $user->email])],
        ]);

        $this->reassignAuthorshipAndDelete($user, auth()->id());

        Flux::modal('delete-user')->close();
        Flux::toast('User and their personal jobs deleted.', variant: 'success');
        $this->resetDeleteState();
    }

    public function render()
    {
        return view('livewire.admin.users', [
            'users' => User::orderBy('surname')->orderBy('forenames')->get(),
            'transferCandidates' => User::query()
                ->when($this->deletingUserId, fn ($q) => $q->where('id', '!=', $this->deletingUserId))
                ->orderBy('surname')
                ->orderBy('forenames')
                ->get(),
        ]);
    }

    private function reassignAuthorshipAndDelete(User $user, int $authorshipFallbackUserId): void
    {
        Job::where('created_by_user_id', $user->id)
            ->where(function ($q) use ($user) {
                $q->whereNotNull('team_id')->orWhere('user_id', '!=', $user->id);
            })
            ->update(['created_by_user_id' => $authorshipFallbackUserId]);

        $user->delete();
    }

    private function resetDeleteState(): void
    {
        $this->deletingUserId = null;
        $this->deletingUserName = null;
        $this->deletingUserPersonalJobCount = 0;
        $this->transferTargetUserId = null;
        $this->typedConfirmation = '';
    }
}
