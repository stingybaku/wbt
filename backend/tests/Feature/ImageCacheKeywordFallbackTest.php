<?php

namespace Tests\Feature;

use App\Models\SourcedImage;
use App\Services\EmbeddingService;
use App\Services\ImageCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The keyword fallback used to run one query per token. whereJsonContains
 * cannot use an index on a json column, so each token cost its own scan.
 * These cover the behaviour and the query count.
 */
class ImageCacheKeywordFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function cache(): ImageCacheService
    {
        // Embeddings need a live API key; stub it out so the keyword fallback
        // is what actually runs.
        $embedding = $this->createMock(EmbeddingService::class);
        $embedding->method('embed')->willReturn(null);

        return new ImageCacheService($embedding);
    }

    private function image(string $topic, array $keywords): SourcedImage
    {
        return SourcedImage::create([
            'source'       => 'pexels',
            'external_id'  => 'ext_' . $topic . '_' . implode('_', $keywords),
            'url'          => 'https://example.com/' . $topic . '.jpg',
            'photographer' => 'Someone',
            'license'      => 'Pexels License',
            'topic'        => $topic,
            'keywords'     => $keywords,
        ]);
    }

    public function test_it_finds_an_image_by_an_overlapping_keyword(): void
    {
        $this->image('forklift safety', ['forklift', 'safety']);

        $hit = $this->cache()->find('warehouse forklift');

        $this->assertNotNull($hit);
        $this->assertSame('forklift safety', $hit->topic);
    }

    public function test_it_matches_on_a_later_token_too(): void
    {
        $this->image('fire extinguisher', ['fire', 'extinguisher']);

        // 'unrelated' matches nothing; 'extinguisher' is the second token.
        $hit = $this->cache()->find('unrelated extinguisher');

        $this->assertNotNull($hit);
        $this->assertSame('fire extinguisher', $hit->topic);
    }

    public function test_it_prefers_an_exact_topic_match(): void
    {
        $this->image('forklift safety', ['forklift', 'safety']);
        $this->image('other thing', ['forklift']);

        $hit = $this->cache()->find('Forklift Safety');

        $this->assertSame('forklift safety', $hit->topic);
    }

    public function test_it_returns_null_when_nothing_overlaps(): void
    {
        $this->image('forklift safety', ['forklift', 'safety']);

        $this->assertNull($this->cache()->find('quantum chromodynamics'));
    }

    public function test_the_keyword_fallback_runs_a_single_query(): void
    {
        $this->image('forklift safety', ['forklift', 'safety']);

        DB::enableQueryLog();
        $this->cache()->find('alpha beta gamma delta');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $keywordQueries = collect($queries)
            ->filter(fn ($q) => str_contains($q['query'], 'keywords'))
            ->count();

        $this->assertSame(
            1,
            $keywordQueries,
            'Four tokens must still cost one keyword query, not one per token.'
        );
    }
}
