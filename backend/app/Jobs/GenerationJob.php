<?php

namespace App\Jobs;

use App\Models\Module;
use App\Models\Project;
use App\Services\GenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries   = 1; // Retries are handled inside GenerationService

    public function __construct(
        public readonly string $projectId,
        public readonly int    $durationMinutes,
        public readonly string $learningObjectives,
        public readonly int    $assessmentCount,
    ) {}

    public function handle(GenerationService $service): void
    {
        $project = Project::findOrFail($this->projectId);
        $module  = Module::where('project_id', $this->projectId)->firstOrFail();

        if (empty($project->source_text)) {
            $this->markFailed($module, 'No source text available for generation.');
            return;
        }

        try {
            $moduleJson = $service->generate(
                sourceText:         $project->source_text,
                durationMinutes:    $this->durationMinutes,
                learningObjectives: $this->learningObjectives,
                assessmentCount:    $this->assessmentCount,
                projectTitle:       $project->title,
            );

            $moduleJson = $this->enrichImages($moduleJson);

            $module->update([
                'module_json'      => $moduleJson,
                'status'           => 'generated',
                'generation_error' => null,
            ]);

            Log::info("GenerationJob succeeded for project {$this->projectId}");
        } catch (\Throwable $e) {
            $this->markFailed($module, $e->getMessage());
            Log::error("GenerationJob failed for project {$this->projectId}: " . $e->getMessage());
        }
    }

    private function enrichImages(array $moduleJson): array
    {
        $accessKey = config('services.unsplash.access_key');

        if (!$accessKey) {
            return $moduleJson;
        }

        foreach ($moduleJson['screens'] as &$screen) {
            if ($screen['type'] !== 'image_text') {
                continue;
            }

            $query = $screen['title'] ?? '';
            if (!$query) {
                continue;
            }

            try {
                $res = Http::timeout(8)->get('https://api.unsplash.com/search/photos', [
                    'query'     => $query,
                    'per_page'  => 1,
                    'client_id' => $accessKey,
                ]);

                $results = $res->json('results', []);
                if (empty($results)) {
                    continue;
                }

                $photo = $results[0];
                $screen['image'] = [
                    'url'      => $photo['urls']['regular'],
                    'alt_text' => $photo['alt_description'] ?? $photo['description'] ?? $query,
                ];
                $screen['accessibility']['alt_text'] = $screen['image']['alt_text'];

                $moduleJson['attribution']['unsplash'][] = [
                    'image_id'     => $photo['id'],
                    'photographer' => $photo['user']['name'],
                    'url'          => $photo['links']['html'],
                    'license'      => 'Unsplash',
                ];
            } catch (\Throwable $e) {
                Log::warning("Image enrichment failed for screen {$screen['id']}: " . $e->getMessage());
            }
        }

        // Deduplicate attribution by image_id
        $seen = [];
        $moduleJson['attribution']['unsplash'] = array_values(
            array_filter($moduleJson['attribution']['unsplash'], function ($entry) use (&$seen) {
                if (isset($seen[$entry['image_id']])) return false;
                $seen[$entry['image_id']] = true;
                return true;
            })
        );

        return $moduleJson;
    }

    private function markFailed(Module $module, string $message): void
    {
        $module->update([
            'status'           => 'failed',
            'generation_error' => $message,
        ]);
    }
}
