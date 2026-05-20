<div class="max-w-3xl">
    <flux:heading size="xl">Admin</flux:heading>

    <flux:separator class="my-6" />

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <a href="{{ route('admin.teams.index') }}" wire:navigate>
            <flux:card>
                <flux:heading size="sm">Teams</flux:heading>
                <flux:text size="sm" class="mt-1">Create teams, manage membership, silence as a group.</flux:text>
            </flux:card>
        </a>

        <a href="{{ route('admin.users.index') }}" wire:navigate>
            <flux:card>
                <flux:heading size="sm">Users</flux:heading>
                <flux:text size="sm" class="mt-1">Edit, promote and remove user accounts.</flux:text>
            </flux:card>
        </a>

        <a href="{{ route('admin.api-tokens.index') }}" wire:navigate>
            <flux:card>
                <flux:heading size="sm">API tokens</flux:heading>
                <flux:text size="sm" class="mt-1">Audit and revoke API tokens across every user.</flux:text>
            </flux:card>
        </a>
    </div>
</div>
