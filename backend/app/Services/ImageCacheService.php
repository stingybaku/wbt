<?php

namespace App\Services;

use App\Models\SourcedImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageCacheService
{
    public function __construct(private EmbeddingService $embedding) {}

    /**
     * Find a cached image for the given query using semantic similarity.
     * Falls back to exact topic match, then keyword overlap if embeddings unavailable.
     */
    public function find(string $query): ?SourcedImage
    {
        $normalized = $this->normalize($query);

        $vector = $this->embedding->embed($normalized);
        if ($vector) {
            $literal = $this->embedding->toVectorLiteral($vector);
            $hit = SourcedImage::selectRaw('*, (embedding <=> ?) AS distance', [$literal])
                ->whereNotNull(DB::raw('embedding'))
                ->orderBy('distance')
                ->first();

            if ($hit && $hit->distance < 0.18) {
                return $this->ensureR2($hit);
            }
        }

        $hit = SourcedImage::where('topic', $normalized)->inRandomOrder()->first();
        if ($hit) return $this->ensureR2($hit);

        $tokens = array_filter(explode(' ', $normalized));
        foreach ($tokens as $token) {
            $hit = SourcedImage::whereJsonContains('keywords', $token)->inRandomOrder()->first();
            if ($hit) return $this->ensureR2($hit);
        }

        return null;
    }

    /**
     * Upload to R2 if the record has no stored_path yet, then return it.
     */
    private function ensureR2(SourcedImage $image): SourcedImage
    {
        if (!$image->stored_path) {
            $storedPath = $this->uploadToR2($image->url, $image->source, $image->external_id);
            if ($storedPath) {
                $image->update(['stored_path' => $storedPath]);
            }
        }
        return $image;
    }

    /**
     * Store a new image in the cache, download it to R2, and generate its embedding.
     */
    public function store(
        string $query,
        string $source,
        string $externalId,
        string $url,
        ?string $photographer,
        string $license
    ): SourcedImage {
        $normalized = $this->normalize($query);
        $keywords   = array_values(array_unique(array_filter(explode(' ', $normalized))));

        $image = SourcedImage::updateOrCreate(
            ['source' => $source, 'external_id' => $externalId],
            [
                'url'          => $url,
                'photographer' => $photographer,
                'license'      => $license,
                'topic'        => $normalized,
                'keywords'     => $keywords,
            ]
        );

        // Download to R2 if not already stored
        if (!$image->stored_path) {
            $storedPath = $this->uploadToR2($url, $source, $externalId);
            if ($storedPath) {
                $image->update(['stored_path' => $storedPath]);
            }
        }

        // Generate embedding asynchronously
        $vector = $this->embedding->embed($normalized);
        if ($vector) {
            try {
                $literal = $this->embedding->toVectorLiteral($vector);
                DB::statement(
                    'UPDATE sourced_images SET embedding = ? WHERE id = ?',
                    [$literal, $image->id]
                );
            } catch (\Throwable $e) {
                Log::warning('ImageCacheService: failed to store embedding: ' . $e->getMessage());
            }
        }

        return $image;
    }

    /**
     * Download an image from an external URL and upload it to R2.
     * Returns the storage path on success, null on failure.
     */
    private function uploadToR2(string $url, string $source, string $externalId): ?string
    {
        if (!config('filesystems.disks.r2.key')) {
            return null;
        }

        try {
            $response = Http::timeout(15)->get($url);
            if (!$response->successful()) {
                Log::warning("ImageCacheService: failed to download image from {$url}: HTTP {$response->status()}");
                return null;
            }

            $contentType = $response->header('Content-Type') ?? 'image/jpeg';
            $ext = $this->extensionFromContentType($contentType);
            $path = "images/{$source}/{$externalId}.{$ext}";

            Storage::disk('r2')->put($path, $response->body(), [
                'visibility'  => 'public',
                'ContentType' => $contentType,
            ]);

            Log::info("ImageCacheService: uploaded {$path} to R2");
            return $path;
        } catch (\Throwable $e) {
            Log::warning("ImageCacheService: R2 upload failed for {$url}: " . $e->getMessage());
            return null;
        }
    }

    private function extensionFromContentType(string $contentType): string
    {
        return match (true) {
            str_contains($contentType, 'png')  => 'png',
            str_contains($contentType, 'gif')  => 'gif',
            str_contains($contentType, 'webp') => 'webp',
            default                            => 'jpg',
        };
    }

    private function normalize(string $query): string
    {
        return strtolower(trim($query));
    }
}
