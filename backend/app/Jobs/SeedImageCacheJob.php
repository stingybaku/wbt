<?php

namespace App\Jobs;

use App\Services\ImageCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Adds the images referenced by a saved module to the shared image library.
 *
 * This runs on the queue because ImageCacheService::store calls the Voyage
 * embeddings API once per new image, with a 10 second timeout each. Doing that
 * inline meant an editor save with several new images held the HTTP response
 * open for tens of seconds, even though nothing in the response depends on it.
 */
class SeedImageCacheJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly array $moduleJson) {}

    public function handle(ImageCacheService $imageCache): void
    {
        $attributionMap = $this->attributionMap();

        if ($attributionMap === []) {
            return;
        }

        foreach ($this->imageCandidates() as ['query' => $query, 'url' => $url]) {
            if (! $query) {
                continue;
            }

            $attr = $this->matchAttribution($attributionMap, $url);

            if (! $attr) {
                continue;
            }

            try {
                $imageCache->store(
                    $query,
                    $attr['source'],
                    $attr['external_id'],
                    $url,
                    $attr['photographer'],
                    $attr['license'],
                );
            } catch (\Throwable $e) {
                Log::warning("SeedImageCacheJob: failed to cache image for '{$query}': " . $e->getMessage());
            }
        }
    }

    /**
     * Build an image_id => attribution lookup across both sources.
     */
    private function attributionMap(): array
    {
        $map = [];

        foreach (['unsplash', 'pexels'] as $source) {
            foreach ($this->moduleJson['attribution'][$source] ?? [] as $entry) {
                $map[(string) $entry['image_id']] = [
                    'source'       => $source,
                    'photographer' => $entry['photographer'] ?? null,
                    'license'      => $entry['license'] ?? '',
                ];
            }
        }

        return $map;
    }

    /**
     * Collect (query, url) pairs from screen images and click_reveal item
     * images. The screen or item title becomes the cache topic.
     *
     * @return array<int, array{query: string, url: string}>
     */
    private function imageCandidates(): array
    {
        $candidates = [];

        foreach ($this->moduleJson['screens'] ?? [] as $screen) {
            $url = $screen['image']['url'] ?? '';
            if ($url && ! str_contains($url, 'placeholder')) {
                $candidates[] = ['query' => $screen['title'] ?? '', 'url' => $url];
            }

            foreach ($screen['interactions'] ?? [] as $interaction) {
                if (($interaction['kind'] ?? '') !== 'click_reveal') {
                    continue;
                }

                foreach ($interaction['items'] ?? [] as $item) {
                    $itemUrl = $item['image']['url'] ?? '';
                    if ($itemUrl && ! str_contains($itemUrl, 'placeholder')) {
                        $candidates[] = ['query' => $item['title'] ?? '', 'url' => $itemUrl];
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * Match a URL to its attribution entry by checking whether the entry's
     * image_id appears in the URL - the same approach used elsewhere.
     */
    private function matchAttribution(array $attributionMap, string $url): ?array
    {
        foreach ($attributionMap as $imageId => $entry) {
            if (str_contains($url, (string) $imageId)) {
                return array_merge(['external_id' => (string) $imageId], $entry);
            }
        }

        return null;
    }
}
