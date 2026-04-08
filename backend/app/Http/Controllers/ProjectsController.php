<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Project;

class ProjectsController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->with(['modules' => fn($q) => $q->select('project_id', 'status', 'cover_image_url', 'module_json')])
            ->withCount(['invites' => fn($q) => $q->whereNull('revoked_at')->where('expires_at', '>', now())])
            ->get()
            ->map(function ($project) {
                $module = $project->modules->first();
                $moduleJson = $module?->module_json;
                $data = $project->toArray();
                unset($data['modules']);
                $data['cover_image_url']  = $module?->cover_image_url;
                $data['module_status']    = $module?->status;
                $data['source_summary']   = $moduleJson['meta']['source_summary'] ?? null;
                $data['duration_minutes'] = $moduleJson['meta']['duration_minutes'] ?? null;
                $data['screen_count']     = isset($moduleJson['screens']) ? count($moduleJson['screens']) : null;
                $data['invite_count']     = $project->invites_count;
                unset($data['invites_count']);
                return $data;
            });

        return response()->json(['projects' => $projects]);
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
            ->with(['modules' => fn($q) => $q->select('project_id', 'status', 'cover_image_url')])
            ->firstOrFail();

        $module = $project->modules->first();
        $data = $project->toArray();
        unset($data['modules']);
        $data['cover_image_url'] = $module?->cover_image_url;
        $data['module_status']   = $module?->status;

        return response()->json(['project' => $data]);
    }
}
