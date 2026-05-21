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

        return $user->teams()->whereKey($server->team_id)->exists();
    }
}
