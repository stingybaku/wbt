<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PexelsService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.pexels.api_key', '');
    }

    public function searchPhoto(string $query): ?array
    {
        if (!$this->apiKey) return null;

        try {
            $res = Http::withHeaders(['Authorization' => $this->apiKey])
                ->timeout(8)
                ->get('https://api.pexels.com/v1/search', [
                    'query'       => $query,
                    'per_page'    => 3,
                    'orientation' => 'landscape',
                ]);

            $photos = $res->json('photos', []);
            if (empty($photos)) return null;

            $photo = $photos[array_rand($photos)];
            return [
                'external_id'  => (string) $photo['id'],
                'url'          => $photo['src']['large2x'] ?? $photo['src']['original'],
                'photographer' => $photo['photographer'],
                'license'      => 'Pexels License',
                'source'       => 'pexels',
                'image_tags'   => $this->keywordsFromAlt($photo['alt'] ?? ''),
            ];
        } catch (\Throwable $e) {
            Log::warning('PexelsService: photo search failed: ' . $e->getMessage());
            return null;
        }
    }

    private function keywordsFromAlt(string $alt): array
    {
        if (!$alt) return [];
        static $stopWords = ['a','an','the','of','in','on','at','to','for','and','or','with','is','are','was','be','by','as','it','its','from','this','that','into'];
        $words = preg_split('/\W+/', strtolower($alt), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($words, fn($w) => strlen($w) > 2 && !in_array($w, $stopWords)));
    }

    /**
     * Video search — wired up for future video screen type. Not consumed by enrichImages yet.
     */
    public function searchVideo(string $query): ?array
    {
        if (!$this->apiKey) return null;

        try {
            $res = Http::withHeaders(['Authorization' => $this->apiKey])
                ->timeout(8)
                ->get('https://api.pexels.com/videos/search', [
                    'query'    => $query,
                    'per_page' => 1,
                ]);

            $videos = $res->json('videos', []);
            if (empty($videos)) return null;

            $video = $videos[0];
            $files = collect($video['video_files'] ?? [])->sortByDesc('width')->first();

            return [
                'external_id'  => (string) $video['id'],
                'url'          => $files['link'] ?? null,
                'photographer' => $video['user']['name'] ?? null,
                'license'      => 'Pexels License',
                'source'       => 'pexels',
            ];
        } catch (\Throwable $e) {
            Log::warning('PexelsService: video search failed: ' . $e->getMessage());
            return null;
        }
    }
}
