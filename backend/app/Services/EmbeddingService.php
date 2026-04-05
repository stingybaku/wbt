<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private string $apiKey;
    private string $model = 'voyage-3-lite'; // 512 dimensions

    public function __construct()
    {
        $this->apiKey = config('services.voyage.api_key', '');
    }

    /**
     * Generate a 512-dimension embedding vector for a text query.
     * Returns null if the API key is not configured or the call fails.
     */
    public function embed(string $text): ?array
    {
        if (!$this->apiKey) {
            Log::warning('EmbeddingService: VOYAGE_API_KEY is not configured.');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->timeout(10)
            ->post('https://api.voyageai.com/v1/embeddings', [
                'input' => [$text],
                'model' => $this->model,
            ]);

            return $response->json('data.0.embedding');
        } catch (\Throwable $e) {
            Log::warning('EmbeddingService: failed to generate embedding: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Format a float array as a pgvector literal string, e.g. "[0.1,0.2,...]"
     */
    public function toVectorLiteral(array $embedding): string
    {
        return '[' . implode(',', $embedding) . ']';
    }
}
