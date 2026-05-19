<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    public function view(User $user, Job $job): bool
    {
        return $this->hasAccess($user, $job);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Job $job): bool
    {
        return $this->hasAccess($user, $job);
    }

    public function delete(User $user, Job $job): bool
    {
        return $this->hasAccess($user, $job);
    }

    private function hasAccess(User $user, Job $job): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if ($job->user_id && $job->user_id === $user->id) {
            return true;
        }

        if ($job->team_id && $user->teams()->whereKey($job->team_id)->exists()) {
            return true;
        }

        return false;
    }
}
