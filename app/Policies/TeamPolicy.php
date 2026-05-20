<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function view(User $user, Team $team): bool
    {
        if ($user->is_admin) {
            return true;
        }

        return $user->teams()->whereKey($team->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Team $team): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->is_admin;
    }
}
