<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Event;
use App\Models\Project;

class EventsController extends Controller
{
    public function store(Request $request, string $projectId)
    {
        $data = $request->validate([
            'type' => 'required|string',
            'meta' => 'nullable|array'
        ]);

        $project = Project::findOrFail($projectId);
        $event = Event::create([
            'id' => 'evt_' . Str::uuid()->toString(),
            'project_id' => $project->id,
            'type' => $data['type'],
            'meta' => $data['meta'] ?? []
        ]);

        return response()->json([
            'project_id' => $projectId,
            'event' => $event
        ], 201);
    }
}
