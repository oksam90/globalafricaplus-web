<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(?User $user, Project $project): bool
    {
        if ($project->status === 'published') return true;
        return $user && ($project->user_id === $user->id || $user->hasRole('admin'));
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['entrepreneur', 'admin']);
    }

    public function update(User $user, Project $project): bool
    {
        return $project->user_id === $user->id || $user->hasRole('admin');
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->user_id === $user->id || $user->hasRole('admin');
    }

    public function publish(User $user, Project $project): bool
    {
        return $project->user_id === $user->id || $user->hasRole('admin');
    }
}
