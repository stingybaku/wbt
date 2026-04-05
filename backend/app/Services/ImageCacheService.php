<?php

namespace App\Services;

use App\Models\SourcedImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // Try semantic search first (requires embedding)
        $vector = $this->embedding->embed($normalized);
        if ($vector) {
            $literal = $this->embedding->toVectorLiteral($vector);
            $hit = SourcedImage::selectRaw('*, (embedding <=> ?) AS distance', [$literal])
                ->whereNotNull(DB::raw('embedding'))
                ->orderBy('distance')
                ->first();

            if ($hit && $hit->distance < 0.18) { // cosine distance < 0.18 ≈ similarity > 0.82
                return $hit;
            }
        }

        // Fallback: exact topic match
        $hit = SourcedImage::where('topic', $normalized)->inRandomOrder()->first();
        if ($hit) return $hit;

        // Fallback: keyword overlap
        $tokens = array_filter(explode(' ', $normalized));
        foreach ($tokens as $token) {
            $hit = SourcedImage::whereJsonContains('keywords', $token)->inRandomOrder()->first();
            if ($hit) return $hit;
        }

        return null;
    }

    /**
     * Store a new image in the cache and generate its embedding asynchronously.
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

        // Generate and store embedding synchronously (small overhead, big cache benefit)
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

    private function normalize(string $query): string
    {
        return strtolower(trim($query));
    }
}
