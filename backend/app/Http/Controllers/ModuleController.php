<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Jobs\GenerationJob;
use App\Models\Module;
use App\Models\Project;

class ModuleController extends Controller
{
    public function generate(Request $request, string $projectId)
    {
        $data = $request->validate([
            'duration_minutes'    => 'nullable|integer|min:10|max:30',
            'learning_objectives' => 'nullable|string',
            'assessment_count'    => 'nullable|integer|min:0|max:10',
        ]);

        $project = Project::where('id', $projectId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (empty($project->source_text)) {
            return response()->json(['message' => 'Upload a content source before generating.'], 422);
        }

        // Create or reset the module record
        $module = Module::firstOrNew(['project_id' => $project->id]);
        if (!$module->exists) {
            $module->id = 'mod_' . Str::uuid()->toString();
        }
        $module->status           = 'generating';
        $module->module_json      = null;
        $module->generation_error = null;
        $module->save();

        GenerationJob::dispatch(
            projectId:          $project->id,
            durationMinutes:    $data['duration_minutes'] ?? 20,
            learningObjectives: $data['learning_objectives'] ?? '',
            assessmentCount:    $data['assessment_count'] ?? 5,
        );

        return response()->json(['status' => 'generating'], 202);
    }

    public function show(string $projectId)
    {
        $module = Module::where('project_id', $projectId)->first();

        return response()->json([
            'module' => $module ? [
                'id'               => $module->id,
                'project_id'       => $module->project_id,
                'status'           => $module->status,
                'module_json'      => $module->module_json,
                'generation_error' => $module->generation_error ?? null,
                'created_at'       => $module->created_at,
                'updated_at'       => $module->updated_at,
            ] : null,
        ]);
    }

    public function update(Request $request, string $projectId)
    {
        $data = $request->validate([
            'module_json' => 'required|array',
        ]);

        $project = Project::where('id', $projectId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $module = Module::where('project_id', $project->id)->firstOrFail();
        $module->module_json = $data['module_json'];

        // Sync module cover image if the editor set one explicitly in meta
        if (!empty($data['module_json']['meta']['cover_image'])) {
            $module->cover_image_url = $data['module_json']['meta']['cover_image'];
        }

        $module->save();

        return response()->json([
            'module' => [
                'id'               => $module->id,
                'project_id'       => $module->project_id,
                'status'           => $module->status,
                'module_json'      => $module->module_json,
                'generation_error' => $module->generation_error ?? null,
                'created_at'       => $module->created_at,
                'updated_at'       => $module->updated_at,
            ],
        ]);
    }
}
