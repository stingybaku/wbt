<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Module;
use App\Models\Project;
use App\Notifications\GenerationCompleteNotification;
use Illuminate\Support\Str;
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
            $moduleJson = $this->enrichVideos($moduleJson);
            $moduleJson = $this->assignLayouts($moduleJson);

            $module->update([
                'module_json'      => $moduleJson,
                'status'           => 'generated',
                'generation_error' => null,
                'cover_image_url'  => $this->extractCoverImage($moduleJson),
            ]);

            $project->user->notify(new GenerationCompleteNotification($project));

            Event::create([
                'id'         => 'evt_' . Str::uuid(),
                'project_id' => $this->projectId,
                'type'       => 'module.generated',
                'meta'       => ['screen_count' => count($moduleJson['screens'] ?? [])],
            ]);

            Log::info("GenerationJob succeeded for project {$this->projectId}");
        } catch (\Throwable $e) {
            $this->markFailed($module, $e->getMessage());
            $project->user->notify(new GenerationFailedNotification($project));

            Event::create([
                'id'         => 'evt_' . Str::uuid(),
                'project_id' => $this->projectId,
                'type'       => 'module.failed',
                'meta'       => ['error' => $e->getMessage()],
            ]);

            Log::error("GenerationJob failed for project {$this->projectId}: " . $e->getMessage());
        }
    }

    /**
     * Fetch an image for the given query.
     * Order: cache → Pexels → Unsplash.
     * Skips any URL already used in the current module run (passed by reference).
     *
     * Returns an array with keys: url, alt_text, source, external_id, photographer, license
     * Returns null if all sources are exhausted or produce duplicates.
     */
    private function fetchImage(string $query, array &$usedUrls, ?string $fallbackQuery = null): ?array
    {
        $refined = $this->refineImageQuery($query);

        // If refinement reduced the title to a single vague word (signalled by the
        // " concept" grounding suffix), substitute the module-level topic instead.
        if ($fallbackQuery && str_ends_with($refined, ' concept')) {
            $refined = $fallbackQuery;
        }

        // 1. Check the semantic/keyword cache — skip if URL already used in this module
        $hit = $this->cache->find($refined);
        if ($hit && !in_array($hit->url, $usedUrls)) {
            $usedUrls[] = $hit->url;
            return [
                'url'          => $hit->url,
                'alt_text'     => $hit->topic,
                'source'       => $hit->source,
                'external_id'  => $hit->external_id,
                'photographer' => $hit->photographer,
                'license'      => $hit->license,
            ];
        }

        // 2. Try Pexels first (higher free-tier rate limit)
        $pexelsResult = $this->pexels->searchPhoto($refined);
        if ($pexelsResult && !in_array($pexelsResult['url'], $usedUrls)) {
            $this->cache->store(
                $refined,
                'pexels',
                $pexelsResult['external_id'],
                $pexelsResult['url'],
                $pexelsResult['photographer'],
                $pexelsResult['license']
            );
            $usedUrls[] = $pexelsResult['url'];
            return [
                'url'          => $pexelsResult['url'],
                'alt_text'     => $refined,
                'source'       => 'pexels',
                'external_id'  => $pexelsResult['external_id'],
                'photographer' => $pexelsResult['photographer'],
                'license'      => $pexelsResult['license'],
            ];
        }

        // 3. Fall back to Unsplash
        $accessKey = config('services.unsplash.access_key');
        if ($accessKey) {
            try {
                $res = Http::timeout(8)->get('https://api.unsplash.com/search/photos', [
                    'query'       => $refined,
                    'per_page'    => 3,
                    'orientation' => 'landscape',
                    'client_id'   => $accessKey,
                ]);

                foreach ($res->json('results', []) as $photo) {
                    $url = $photo['urls']['regular'];
                    if (in_array($url, $usedUrls)) continue;

                    $this->cache->store(
                        $refined,
                        'unsplash',
                        $photo['id'],
                        $url,
                        $photo['user']['name'],
                        'Unsplash License'
                    );
                    $usedUrls[] = $url;
                    return [
                        'url'          => $url,
                        'alt_text'     => $photo['alt_description'] ?? $photo['description'] ?? $refined,
                        'source'       => 'unsplash',
                        'external_id'  => $photo['id'],
                        'photographer' => $photo['user']['name'],
                        'license'      => 'Unsplash License',
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning("fetchImage: Unsplash failed for '{$refined}': " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Derive a concise topic string from the module metadata for use as a fallback
     * image query when individual screen titles are too generic (e.g. "Welcome").
     *
     * Tries meta.title first; if that also reduces to a vague single word, falls
     * back to the first sentence of meta.source_summary.
     */
    private function extractModuleTopic(array $meta): string
    {
        $refined = $this->refineImageQuery($meta['title'] ?? '');

        // Title was too generic — try the source summary instead
        if (str_ends_with($refined, ' concept')) {
            $summary = trim($meta['source_summary'] ?? '');
            if ($summary) {
                $firstSentence = trim(explode('.', $summary)[0]);
                $refined = $this->refineImageQuery($firstSentence);
            }
        }

        // Strip the " concept" grounding suffix — it's appended for standalone
        // queries but here we return just the bare topic words.
        if (str_ends_with($refined, ' concept')) {
            $refined = substr($refined, 0, -strlen(' concept'));
        }

        return trim($refined);
    }

    /**
     * Refine a screen title into an image search query that avoids people-centric
     * and racially specific results. Also strips child-related terms unless the
     * content is genuinely about children/youth (detected from the original query).
     */
    private function refineImageQuery(string $query): string
    {
        $lower = strtolower(trim($query));

        $peopleTerms = [
            'employee', 'employees', 'worker', 'workers', 'staff', 'staffing',
            'team', 'teams', 'group', 'groups', 'people', 'person', 'persons',
            'man', 'men', 'woman', 'women', 'individual', 'individuals',
            'manager', 'managers', 'supervisor', 'supervisors', 'leader', 'leaders',
            'executive', 'executives', 'professional', 'professionals',
            'nurse', 'nurses', 'doctor', 'doctors', 'patient', 'patients',
            'student', 'students', 'teacher', 'teachers', 'instructor', 'instructors',
            'customer', 'customers', 'client', 'clients', 'user', 'users',
            'colleague', 'colleagues', 'member', 'members', 'participant', 'participants',
            'operator', 'operators', 'technician', 'technicians',
        ];

        // Strip child-related terms unless the content is actually about children
        if (!$this->isChildRelatedContent($lower)) {
            $peopleTerms = array_merge($peopleTerms, [
                'child', 'children', 'kid', 'kids', 'boy', 'boys', 'girl', 'girls',
                'baby', 'babies', 'infant', 'infants', 'toddler', 'toddlers',
                'teen', 'teens', 'teenager', 'teenagers', 'adolescent', 'adolescents',
                'juvenile', 'juveniles', 'minor', 'minors',
            ]);
        }

        $stopWords = [
            // Articles / prepositions / conjunctions
            'the', 'a', 'an', 'for', 'to', 'of', 'in', 'on', 'at', 'by',
            'and', 'or', 'with', 'new', 'your', 'our', 'their', 'its', 'is',
            'how', 'why', 'what', 'when', 'where', 'who', 'that', 'this',
            // Generic lesson-structure words (never useful in an image search)
            'introduction', 'overview', 'understanding', 'effective', 'essential',
            'key', 'basic', 'basics', 'fundamental', 'fundamentals',
            'principle', 'principles', 'aspect', 'aspects', 'element', 'elements',
            'approach', 'approaches', 'strategy', 'strategies', 'concept', 'concepts',
            'method', 'methods', 'process', 'processes', 'procedure', 'procedures',
            'step', 'steps', 'stage', 'stages', 'phase', 'phases',
            'type', 'types', 'kind', 'kinds', 'form', 'forms',
            'role', 'roles', 'importance', 'impact', 'benefit', 'benefits',
            'best', 'practice', 'practices', 'guideline', 'guidelines',
            'general', 'common', 'main', 'major', 'various', 'different',
            'important', 'critical', 'significant', 'relevant', 'applicable',
            'training', 'course', 'module', 'lesson', 'section', 'unit', 'topic',
            // Greeting / structural words that produce generic unrelated stock photos
            'welcome', 'hello', 'greetings', 'intro',
            'conclusion', 'conclusions', 'summary', 'summaries', 'recap', 'closing',
        ];

        $words    = preg_split('/\s+/', $lower);
        $filtered = array_filter($words, fn($w) =>
            $w !== '' &&
            !in_array($w, $peopleTerms) &&
            !in_array($w, $stopWords)
        );

        $refined = implode(' ', $filtered);

        // If stripping left too little, just remove people terms but keep stop words
        if (strlen($refined) < 4) {
            $refined = implode(' ', array_filter($words, fn($w) =>
                $w !== '' && !in_array($w, $peopleTerms)
            ));
        }

        $refined = trim($refined) ?: $lower;

        // Limit to 4 meaningful words — enough context without diluting relevance
        $refinedWords = preg_split('/\s+/', $refined);
        if (count($refinedWords) > 4) {
            $refined = implode(' ', array_slice($refinedWords, 0, 4));
        }

        // Append a grounding suffix for very abstract single-word queries so stock
        // photo engines return conceptual/workplace images rather than literal portraits
        if (count(preg_split('/\s+/', $refined)) === 1 && strlen($refined) > 3) {
            $refined .= ' concept';
        }

        return $refined;
    }

    /**
     * Detect whether a query is genuinely about children/youth content,
     * in which case child imagery should be permitted.
     */
    private function isChildRelatedContent(string $lower): bool
    {
        $childTopics = [
            'child', 'children', 'kid', 'kids', 'youth', 'young learner',
            'pediatric', 'paediatric', 'infant', 'toddler', 'baby',
            'teen', 'teenager', 'adolescent', 'juvenile',
            'school', 'kindergarten', 'nursery', 'daycare', 'childcare',
            'parenting', 'child development', 'child safety', 'safeguarding',
        ];

        foreach ($childTopics as $term) {
            if (str_contains($lower, $term)) return true;
        }

        return false;
    }

    private function enrichImages(array $moduleJson): array
    {
        $attempted = 0;
        $succeeded = 0;
        $skippedNoResults = 0;
        $skippedError = 0;

        // Track URLs already assigned in this module to prevent repetition within a course.
        // Images can still be reused across different courses via the cache.
        $usedUrls = [];

        // Module-level topic used as a fallback when a screen title is too generic
        // (e.g. "Welcome", "Conclusion") to produce a meaningful image query.
        $moduleTopic = $this->extractModuleTopic($moduleJson['meta'] ?? []);

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
                $usedUrls[] = $existingUrl; // still mark as used
                continue;
            }

            // Use screen title as query; fall back to screen type for generic imagery
            $query = trim($screen['title'] ?? '');
            if (!$query) {
                continue;
            }

            $attempted++;
            try {
                $result = $this->fetchImage($query, $usedUrls, $moduleTopic ?: null);

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
                        $usedUrls[] = $existingUrl;
                        continue;
                    }

                    $query = trim($item['title'] ?? '');
                    if (!$query) continue;

                    $attempted++;
                    try {
                        $result = $this->fetchImage($query, $usedUrls, $moduleTopic ?: null);

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

    private function enrichVideos(array $moduleJson): array
    {
        foreach ($moduleJson['screens'] as &$screen) {
            if ($screen['type'] !== 'video') continue;

            $interaction = &$screen['interactions'][0];
            if (empty($interaction['url'])) {
                $query  = $screen['title'];
                $result = $this->pexels->searchVideo($query);
                if ($result && !empty($result['url'])) {
                    $interaction['url'] = $result['url'];
                    Log::info("enrichVideos: fetched Pexels video for '{$query}'");
                } else {
                    Log::warning("enrichVideos: no Pexels video found for '{$query}'");
                }
            }
        }
        unset($screen, $interaction);

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
