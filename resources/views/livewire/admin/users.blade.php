<div class="max-w-4xl">
    <flux:heading size="xl">Users</flux:heading>
    <flux:text class="mt-2">Promote / demote admins. Users sign in via SSO — there's no manual add.</flux:text>

    <flux:table class="mt-6">
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Admin</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($users as $user)
                <flux:table.row wire:key="user-row-{{ $user->id }}">
                    <flux:table.cell>{{ $user->full_name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($user->is(auth()->user()))
                            <flux:switch :checked="$user->is_admin" disabled />
                        @else
                            <flux:switch :checked="$user->is_admin" wire:click="toggleAdmin({{ $user->id }})" />
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
