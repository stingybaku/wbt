<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Jobs\GenerationJob;
use App\Models\Module;
use App\Models\Project;
use App\Services\ImageCacheService;

class ModuleController extends Controller
{
    public function __construct(private ImageCacheService $imageCache) {}

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

        $this->seedImageCache($data['module_json']);

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

    /**
     * Cache any images present in the saved module JSON that are not already
     * in the library. Only fires embedding API calls for genuinely new images
     * (updateOrCreate is a no-op for existing ones).
     *
     * Images are matched to their attribution entry by checking whether the
     * attribution's image_id appears in the URL — the same approach used
     * throughout the codebase. The screen/item title becomes the cache topic.
     */
    private function seedImageCache(array $moduleJson): void
    {
        // Build id → attribution lookup across both sources
        $attributionMap = [];
        foreach (['unsplash', 'pexels'] as $source) {
            foreach ($moduleJson['attribution'][$source] ?? [] as $entry) {
                $attributionMap[(string) $entry['image_id']] = [
                    'source'       => $source,
                    'photographer' => $entry['photographer'] ?? null,
                    'license'      => $entry['license'] ?? '',
                ];
            }
        }

        if (empty($attributionMap)) return;

        // Collect (query, url) pairs from screen images and click_reveal item images
        $candidates = [];
        foreach ($moduleJson['screens'] ?? [] as $screen) {
            $url = $screen['image']['url'] ?? '';
            if ($url && !str_contains($url, 'placeholder')) {
                $candidates[] = ['query' => $screen['title'] ?? '', 'url' => $url];
            }

            foreach ($screen['interactions'] ?? [] as $interaction) {
                if (($interaction['kind'] ?? '') !== 'click_reveal') continue;
                foreach ($interaction['items'] ?? [] as $item) {
                    $itemUrl = $item['image']['url'] ?? '';
                    if ($itemUrl && !str_contains($itemUrl, 'placeholder')) {
                        $candidates[] = ['query' => $item['title'] ?? '', 'url' => $itemUrl];
                    }
                }
            }
        }

        foreach ($candidates as ['query' => $query, 'url' => $url]) {
            if (!$query) continue;

            // Match URL to its attribution entry via image_id substring
            $attr = null;
            foreach ($attributionMap as $imageId => $entry) {
                if (str_contains($url, $imageId)) {
                    $attr = array_merge(['external_id' => $imageId], $entry);
                    break;
                }
            }

            if (!$attr) continue;

            try {
                $this->imageCache->store(
                    $query,
                    $attr['source'],
                    $attr['external_id'],
                    $url,
                    $attr['photographer'],
                    $attr['license'],
                );
            } catch (\Throwable $e) {
                Log::warning("ModuleController: failed to cache image for '{$query}': " . $e->getMessage());
            }
        }
    }
}
