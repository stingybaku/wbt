<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function ownedProject(string $id): \App\Models\Project
    {
        $project = \App\Models\Project::findOrFail($id);
        \Illuminate\Support\Facades\Gate::authorize('access', $project);

        return $project;
    }
}
