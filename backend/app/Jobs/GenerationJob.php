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
            $moduleJson = $this->assignLayouts($moduleJson);

            $module->update([
                'module_json'      => $moduleJson,
                'status'           => 'generated',
                'generation_error' => null,
                'cover_image_url'  => $this->extractCoverImage($moduleJson),
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
            Log::warning("Image enrichment skipped: UNSPLASH_ACCESS_KEY is not configured.");
            return $moduleJson;
        }

        $attempted = 0;
        $succeeded = 0;
        $skippedNoResults = 0;
        $skippedError = 0;

        foreach ($moduleJson['screens'] as &$screen) {
            // Skip screens that already have a real (non-placeholder) image
            $existingUrl = $screen['image']['url'] ?? '';
            if ($existingUrl && !str_contains($existingUrl, 'placeholder')) {
                continue;
            }

            // Use screen title as query; fall back to screen type for generic imagery
            $query = trim($screen['title'] ?? '');
            if (!$query) {
                continue;
            }

            $attempted++;
            try {
                $res = Http::timeout(8)->get('https://api.unsplash.com/search/photos', [
                    'query'     => $query,
                    'per_page'  => 1,
                    'client_id' => $accessKey,
                ]);

                $results = $res->json('results', []);
                if (empty($results)) {
                    $skippedNoResults++;
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
                $succeeded++;
            } catch (\Throwable $e) {
                $skippedError++;
                Log::warning("Image enrichment failed for screen {$screen['id']}: " . $e->getMessage());
            }
        }

        // Enrich click_reveal item images
        foreach ($moduleJson['screens'] as &$screen) {
            if ($screen['type'] !== 'click_reveal') continue;

            foreach ($screen['interactions'] as &$interaction) {
                if (($interaction['kind'] ?? '') !== 'click_reveal') continue;

                foreach ($interaction['items'] as &$item) {
                    $existingUrl = $item['image']['url'] ?? '';
                    if ($existingUrl && !str_contains($existingUrl, 'placeholder')) {
                        continue;
                    }

                    $query = trim($item['title'] ?? '');
                    if (!$query) continue;

                    $attempted++;
                    try {
                        $res = Http::timeout(8)->get('https://api.unsplash.com/search/photos', [
                            'query'     => $query,
                            'per_page'  => 1,
                            'client_id' => $accessKey,
                        ]);

                        $results = $res->json('results', []);
                        if (empty($results)) {
                            $skippedNoResults++;
                            continue;
                        }

                        $photo = $results[0];
                        $item['image'] = [
                            'url'      => $photo['urls']['regular'],
                            'alt_text' => $photo['alt_description'] ?? $photo['description'] ?? $query,
                        ];

                        $moduleJson['attribution']['unsplash'][] = [
                            'image_id'     => $photo['id'],
                            'photographer' => $photo['user']['name'],
                            'url'          => $photo['links']['html'],
                            'license'      => 'Unsplash',
                        ];
                        $succeeded++;
                    } catch (\Throwable $e) {
                        $skippedError++;
                        Log::warning("Image enrichment failed for reveal item '{$query}': " . $e->getMessage());
                    }
                }
                unset($item);
            }
            unset($interaction);
        }
        unset($screen);

        Log::info("Image enrichment complete for project {$this->projectId}: " .
            "{$succeeded}/{$attempted} succeeded, " .
            "{$skippedNoResults} no results, " .
            "{$skippedError} errors."
        );

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

    private function assignLayouts(array $moduleJson): array
    {
        $layouts = ['top', 'bottom', 'left', 'right'];

        foreach ($moduleJson['screens'] as &$screen) {
            if ($screen['type'] === 'image_text' && empty($screen['layout'])) {
                $screen['layout'] = $layouts[array_rand($layouts)];
            }
        }

        return $moduleJson;
    }

    private function extractCoverImage(array $moduleJson): ?string
    {
        foreach ($moduleJson['screens'] ?? [] as $screen) {
            $url = $screen['image']['url'] ?? null;
            if ($url && !str_contains($url, 'placeholder')) {
                return $url;
            }
        }
        return null;
    }

    private function markFailed(Module $module, string $message): void
    {
        $module->update([
            'status'           => 'failed',
            'generation_error' => $message,
        ]);
    }
}
