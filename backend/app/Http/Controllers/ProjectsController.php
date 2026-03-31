<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Project;

class ProjectsController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'projects' => Project::where('user_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->get()
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string',
            'rights_attestation' => 'required|boolean'
        ]);

        $project = Project::create([
            'id' => 'proj_' . Str::uuid()->toString(),
            'title' => $data['title'],
            'rights_attestation' => $data['rights_attestation'],
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['project' => $project], 201);
    }

    public function show(Request $request, string $id)
    {
        $project = Project::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json(['project' => $project]);
    }
}
