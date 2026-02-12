<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Module;
use App\Models\Project;

class ModuleController extends Controller
{
    public function generate(Request $request, string $projectId)
    {
        $data = $request->validate([
            'duration_minutes' => 'nullable|integer|min:10|max:30',
            'learning_objectives' => 'nullable|array',
            'assessment_count' => 'nullable|integer|min:0',
            'source_summary' => 'nullable|string'
        ]);

        $screens = [];
        for ($i = 1; $i <= 10; $i++) {
            $screens[] = [
                'id' => 'scr_' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'type' => 'text',
                'title' => 'Screen ' . $i,
                'body' => 'Generated content placeholder.',
                'image' => null,
                'interactions' => [],
                'accessibility' => ['alt_text' => null]
            ];
        }

        $modulePayload = [
            'schema_version' => '1.0',
            'meta' => [
                'title' => 'Generated Module',
                'audience' => 'General',
                'duration_minutes' => $data['duration_minutes'] ?? 20,
                'source_summary' => $data['source_summary'] ?? 'Generated from uploaded content.',
                'created_at' => now()->toIso8601String()
            ],
            'learning_objectives' => $data['learning_objectives'] ?? ['Review generated objectives'],
            'screens' => $screens,
            'assessment' => [
                'questions' => [],
                'passing_score' => 0.8
            ],
            'attribution' => [
                'unsplash' => []
            ],
            'review_flags' => []
        ];

        $project = Project::findOrFail($projectId);
        $module = Module::firstOrNew(['project_id' => $project->id]);
        if (!$module->exists) {
            $module->id = 'mod_' . Str::uuid()->toString();
        }
        $module->module_json = $modulePayload;
        $module->status = 'draft';
        $module->save();

        return response()->json([
            'project_id' => $projectId,
            'module' => $module->module_json
        ], 201);
    }

    public function show(string $projectId)
    {
        $module = Module::where('project_id', $projectId)->first();

        return response()->json([
            'project_id' => $projectId,
            'module' => $module?->module_json
        ]);
    }

    public function update(Request $request, string $projectId)
    {
        $data = $request->validate([
            'module' => 'required|array'
        ]);

        $module = Module::where('project_id', $projectId)->firstOrFail();
        $module->module_json = $data['module'];
        $module->save();

        return response()->json([
            'project_id' => $projectId,
            'module' => $module->module_json
        ]);
    }
}
