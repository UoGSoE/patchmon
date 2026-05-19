<div class="max-w-3xl">
    <flux:heading size="xl">Admin</flux:heading>
    <flux:text class="mt-2">Shortcuts for managing teams and users.</flux:text>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <flux:card>
            <flux:heading size="sm">Teams</flux:heading>
            <flux:text size="sm" class="mt-1">Create teams, manage membership, silence as a group.</flux:text>
            @if (\Illuminate\Support\Facades\Route::has('admin.teams.index'))
                <flux:button :href="route('admin.teams.index')" class="mt-3" wire:navigate>Manage teams</flux:button>
            @endif
        </flux:card>

        <flux:card>
            <flux:heading size="sm">Users</flux:heading>
            <flux:text size="sm" class="mt-1">Edit, promote and remove user accounts.</flux:text>
            @if (\Illuminate\Support\Facades\Route::has('admin.users.index'))
                <flux:button :href="route('admin.users.index')" class="mt-3" wire:navigate>Manage users</flux:button>
            @endif
        </flux:card>
    </div>
</div>
