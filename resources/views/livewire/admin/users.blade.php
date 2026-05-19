<div class="max-w-4xl">
    <flux:heading size="xl">Users</flux:heading>
    <flux:text class="mt-2">Promote / demote admins and toggle the staff flag. Users sign in via SSO — there's no manual add.</flux:text>

    <flux:table class="mt-6">
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Admin</flux:table.column>
            <flux:table.column>Staff</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($users as $user)
                <flux:table.row>
                    <flux:table.cell>{{ $user->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:switch :checked="$user->is_admin" wire:click="toggleAdmin({{ $user->id }})" />
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:switch :checked="$user->is_staff" wire:click="toggleStaff({{ $user->id }})" />
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
