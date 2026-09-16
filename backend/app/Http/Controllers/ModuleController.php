<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Jobs\GenerationJob;
use App\Jobs\SeedImageCacheJob;
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

    public function show(Request $request, string $projectId)
    {
        Project::where('id', $projectId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // The editor polls this every 3 seconds while a module generates, and
        // only needs the status. `?fields=status` leaves module_json - an
        // entire generated course - out of both the query and the response.
        $statusOnly = $request->query('fields') === 'status';

        $columns = ['id', 'project_id', 'status', 'generation_error', 'created_at', 'updated_at'];

        $module = Module::where('project_id', $projectId)
            ->select($statusOnly ? $columns : array_merge($columns, ['module_json']))
            ->first();

        if (! $module) {
            return response()->json(['module' => null]);
        }

        return response()->json([
            'module' => $statusOnly
                ? $this->moduleStatusPayload($module)
                : $this->modulePayload($module),
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

        // Queued: seeding calls the embeddings API once per new image, each with
        // a 10 second timeout, and nothing in this response depends on it.
        SeedImageCacheJob::dispatch($data['module_json']);

        return response()->json([
            'module' => $this->modulePayload($module),
        ]);
    }

    /**
     * The status fields only - no module_json.
     */
    private function moduleStatusPayload(Module $module): array
    {
        return [
            'id'               => $module->id,
            'project_id'       => $module->project_id,
            'status'           => $module->status,
            'generation_error' => $module->generation_error ?? null,
            'created_at'       => $module->created_at,
            'updated_at'       => $module->updated_at,
        ];
    }

    private function modulePayload(Module $module): array
    {
        return [
            'id'               => $module->id,
            'project_id'       => $module->project_id,
            'status'           => $module->status,
            'module_json'      => $module->module_json,
            'generation_error' => $module->generation_error ?? null,
            'created_at'       => $module->created_at,
            'updated_at'       => $module->updated_at,
        ];
    }
}
