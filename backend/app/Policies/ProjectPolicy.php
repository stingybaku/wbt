<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    public function access(User $user, Project $project): Response
    {
        return $user->id === $project->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
