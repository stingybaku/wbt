<?php

namespace Tests\Feature;

use App\Jobs\SeedImageCacheJob;
use App\Models\Module;
use App\Models\Project;
use App\Models\SourcedImage;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Image-cache seeding calls the embeddings API once per new image with a 10
 * second timeout each, so it must not run inside the save request.
 */
class ModuleUpdateSeedsImageCacheTest extends TestCase
{
    use RefreshDatabase;

    private array $moduleJson = [
        'meta'        => ['title' => 'Forklift safety'],
        'screens'     => [
            [
                'id'    => 's1',
                'title' => 'Forklift basics',
                'image' => ['url' => 'https://images.pexels.com/photos/12345/forklift.jpg'],
            ],
        ],
        'attribution' => [
            'pexels' => [
                ['image_id' => '12345', 'photographer' => 'Someone', 'license' => 'Pexels License'],
            ],
        ],
    ];

    private function actAsOwnerWithModule(): Project
    {
        $user = User::factory()->create();

        $project = Project::create([
            'id'                 => 'proj_seed',
            'title'              => 'Seed project',
            'rights_attestation' => true,
            'user_id'            => $user->id,
        ]);

        Module::create([
            'id'         => 'mod_seed',
            'project_id' => $project->id,
            'status'     => 'generated',
        ]);

        Sanctum::actingAs($user);

        return $project;
    }

    public function test_saving_a_module_queues_the_seeding_job_instead_of_running_it_inline(): void
    {
        Queue::fake();

        $project = $this->actAsOwnerWithModule();

        $this->putJson("/api/projects/{$project->id}/module", ['module_json' => $this->moduleJson])
            ->assertOk();

        Queue::assertPushed(SeedImageCacheJob::class);

        // Nothing was written synchronously - that is the job's work now.
        $this->assertSame(0, SourcedImage::count());
    }

    public function test_the_job_stores_the_image_with_its_attribution(): void
    {
        // The embeddings API needs a key; without one embed() returns null and
        // the row is still stored, just without a vector.
        $embedding = $this->createMock(EmbeddingService::class);
        $embedding->method('embed')->willReturn(null);
        $this->app->instance(EmbeddingService::class, $embedding);

        (new SeedImageCacheJob($this->moduleJson))->handle(
            $this->app->make(\App\Services\ImageCacheService::class)
        );

        $image = SourcedImage::sole();

        $this->assertSame('pexels', $image->source);
        $this->assertSame('12345', $image->external_id);
        $this->assertSame('forklift basics', $image->topic);
        $this->assertSame('Someone', $image->photographer);
    }

    public function test_the_job_does_nothing_without_attribution(): void
    {
        $embedding = $this->createMock(EmbeddingService::class);
        $embedding->method('embed')->willReturn(null);
        $this->app->instance(EmbeddingService::class, $embedding);

        (new SeedImageCacheJob(['screens' => [], 'attribution' => []]))->handle(
            $this->app->make(\App\Services\ImageCacheService::class)
        );

        $this->assertSame(0, SourcedImage::count());
    }
}
