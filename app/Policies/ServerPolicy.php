<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;

class ServerPolicy
{
    public function view(User $user, Server $server): bool
    {
        return $this->hasAccess($user, $server);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Server $server): bool
    {
        return $this->hasAccess($user, $server);
    }

    public function delete(User $user, Server $server): bool
    {
        return $this->hasAccess($user, $server);
    }

    private function hasAccess(User $user, Server $server): bool
    {
        if ($user->is_admin) {
            return true;
        }

        // Unassigned (in-triage) servers belong to no team yet, so any user can
        // view and allocate them while they're in triage. Once a team owns it,
        // the usual team-membership rule applies again.
        if ($server->team_id === null) {
            return true;
        }

        return $user->teams()->whereKey($server->team_id)->exists();
    }
}
