<?php

namespace App\Jobs;

use App\Models\Module;
use App\Models\Project;
use App\Notifications\GenerationCompleteNotification;
use App\Notifications\GenerationFailedNotification;
use App\Services\GenerationService;
use App\Services\ImageCacheService;
use App\Services\PexelsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 660; // 3 attempts × 3 min + 3 min image enrichment headroom
    public int $tries   = 1;  // Retries are handled inside GenerationService

    private ImageCacheService $cache;
    private PexelsService $pexels;

    public function __construct(
        public readonly string $projectId,
        public readonly int    $durationMinutes,
        public readonly string $learningObjectives,
        public readonly int    $assessmentCount,
    ) {}

    public function handle(GenerationService $service, ImageCacheService $cache, PexelsService $pexels): void
    {
        $this->cache  = $cache;
        $this->pexels = $pexels;

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

            $project->user->notify(new GenerationCompleteNotification($project));

            Log::info("GenerationJob succeeded for project {$this->projectId}");
        } catch (\Throwable $e) {
            $this->markFailed($module, $e->getMessage());
            $project->user->notify(new GenerationFailedNotification($project));
            Log::error("GenerationJob failed for project {$this->projectId}: " . $e->getMessage());
        }
    }

    /**
     * Fetch an image for the given query from cache, then Unsplash, then Pexels.
     * Stores any fresh result back into the cache.
     *
     * Returns an array with keys: url, alt_text, source, external_id, photographer, license
     * Returns null if all sources fail.
     */
    private function fetchImage(string $query): ?array
    {
        // 1. Check the semantic/keyword cache
        $hit = $this->cache->find($query);
        if ($hit) {
            return [
                'url'          => $hit->url,
                'alt_text'     => $hit->topic,
                'source'       => $hit->source,
                'external_id'  => $hit->external_id,
                'photographer' => $hit->photographer,
                'license'      => $hit->license,
            ];
        }

        // 2. Try Unsplash
        $accessKey = config('services.unsplash.access_key');
        if ($accessKey) {
            try {
                $res = Http::timeout(8)->get('https://api.unsplash.com/search/photos', [
                    'query'     => $query,
                    'per_page'  => 1,
                    'client_id' => $accessKey,
                ]);

                $results = $res->json('results', []);
                if (!empty($results)) {
                    $photo = $results[0];
                    $this->cache->store(
                        $query,
                        'unsplash',
                        $photo['id'],
                        $photo['urls']['regular'],
                        $photo['user']['name'],
                        'Unsplash License'
                    );
                    return [
                        'url'          => $photo['urls']['regular'],
                        'alt_text'     => $photo['alt_description'] ?? $photo['description'] ?? $query,
                        'source'       => 'unsplash',
                        'external_id'  => $photo['id'],
                        'photographer' => $photo['user']['name'],
                        'license'      => 'Unsplash License',
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning("fetchImage: Unsplash failed for '{$query}': " . $e->getMessage());
            }
        }

        // 3. Try Pexels
        $pexelsResult = $this->pexels->searchPhoto($query);
        if ($pexelsResult) {
            $this->cache->store(
                $query,
                'pexels',
                $pexelsResult['external_id'],
                $pexelsResult['url'],
                $pexelsResult['photographer'],
                $pexelsResult['license']
            );
            return [
                'url'          => $pexelsResult['url'],
                'alt_text'     => $query,
                'source'       => 'pexels',
                'external_id'  => $pexelsResult['external_id'],
                'photographer' => $pexelsResult['photographer'],
                'license'      => $pexelsResult['license'],
            ];
        }

        return null;
    }

    private function enrichImages(array $moduleJson): array
    {
        $attempted = 0;
        $succeeded = 0;
        $skippedNoResults = 0;
        $skippedError = 0;

        // Initialize attribution buckets for both sources
        if (!isset($moduleJson['attribution']['unsplash'])) {
            $moduleJson['attribution']['unsplash'] = [];
        }
        if (!isset($moduleJson['attribution']['pexels'])) {
            $moduleJson['attribution']['pexels'] = [];
        }

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
                $result = $this->fetchImage($query);

                if (!$result) {
                    $skippedNoResults++;
                    continue;
                }

                $source = $result['source'];
                $screen['image'] = [
                    'url'      => $result['url'],
                    'alt_text' => $result['alt_text'],
                ];
                $screen['accessibility']['alt_text'] = $screen['image']['alt_text'];

                $moduleJson['attribution'][$source][] = [
                    'image_id'     => $result['external_id'],
                    'photographer' => $result['photographer'],
                    'license'      => $result['license'],
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
                        $result = $this->fetchImage($query);

                        if (!$result) {
                            $skippedNoResults++;
                            continue;
                        }

                        $source = $result['source'];
                        $item['image'] = [
                            'url'      => $result['url'],
                            'alt_text' => $result['alt_text'],
                        ];

                        $moduleJson['attribution'][$source][] = [
                            'image_id'     => $result['external_id'],
                            'photographer' => $result['photographer'],
                            'license'      => $result['license'],
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

        // Deduplicate attribution by image_id per source
        foreach (['unsplash', 'pexels'] as $source) {
            $seen = [];
            $moduleJson['attribution'][$source] = array_values(
                array_filter($moduleJson['attribution'][$source], function ($entry) use (&$seen) {
                    if (isset($seen[$entry['image_id']])) return false;
                    $seen[$entry['image_id']] = true;
                    return true;
                })
            );
        }

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
