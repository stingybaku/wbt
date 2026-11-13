<?php

namespace Tests\Feature;

use App\Jobs\GenerationJob;
use App\Models\GenerationRun;
use App\Models\Module;
use App\Models\User;
use App\Services\GenerationCredits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Fixtures;
use Tests\TestCase;

class GenerationCreditsTest extends TestCase
{
    use Fixtures, RefreshDatabase;

    public function test_reservation_blocks_additional_requests_and_failure_refunds_once(): void
    {
        Queue::fake();
        Notification::fake();
        $user = User::factory()->create();
        $sub = $this->subscription($user);
        $project = $this->project($user);
        $this->actingAs($user)->postJson("/api/projects/{$project->id}/module/generate")->assertAccepted();
        $this->assertSame(0, $sub->fresh()->modulesAvailable());
        $this->postJson("/api/projects/{$project->id}/module/generate")->assertStatus(402);
        $other = $this->project($user);
        $this->postJson("/api/projects/{$other->id}/module/generate")->assertStatus(402);
        Queue::assertPushed(GenerationJob::class, 1);
        $job = Queue::pushed(GenerationJob::class)->first();
        $job->failed(new \RuntimeException('timeout'));
        $job->failed(new \RuntimeException('timeout'));
        $this->assertSame(1, $sub->fresh()->modulesAvailable());
        $this->assertSame(0, $sub->fresh()->modules_reserved);
        $this->assertSame(0, $sub->fresh()->modules_used);
    }

    public function test_success_charges_original_subscription_once_even_after_cancellation(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $sub = $this->subscription($user);
        $project = $this->project($user);
        $credits = app(GenerationCredits::class);
        $run = $credits->enqueue($project, []);
        $this->assertTrue($credits->claim($run->id));
        $this->assertFalse($credits->claim($run->id));
        $sub->update(['status' => 'cancelled']);
        $newSub = $this->subscription($user);
        $this->assertTrue($credits->finish($run->id, ['screens' => []], null));
        $this->assertFalse($credits->finish($run->id, null, null, 'late failure'));
        $this->assertSame(1, $sub->fresh()->modules_used);
        $this->assertSame(0, $sub->fresh()->modules_reserved);
        $this->assertSame(0, $newSub->fresh()->modules_used);
    }

    public function test_same_project_cannot_generate_twice_with_multiple_available_credits(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $sub = $this->subscription($user, ['bonus_modules' => 2]);
        $project = $this->project($user);
        $this->actingAs($user)->postJson("/api/projects/{$project->id}/module/generate")->assertAccepted();
        $this->postJson("/api/projects/{$project->id}/module/generate")->assertStatus(409);
        $this->assertSame(1, $sub->fresh()->modules_reserved);
    }

    public function test_notification_failure_cannot_refund_a_successful_generation(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $sub = $this->subscription($user);
        $project = $this->project($user);
        app(GenerationCredits::class)->enqueue($project, []);
        $job = Queue::pushed(GenerationJob::class)->first();
        $generator = \Mockery::mock(\App\Services\GenerationService::class);
        $generator->shouldReceive('generate')->once()->andReturn(['screens' => []]);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('mail unavailable'));
        $job->handle($generator, app(\App\Services\ImageCacheService::class), app(\App\Services\PexelsService::class));
        $this->assertSame(1, $sub->fresh()->modules_used);
        $this->assertSame(0, $sub->fresh()->modules_reserved);
        $this->assertDatabaseHas('generation_runs', ['id' => $job->generationRunId, 'status' => 'succeeded']);
        $this->assertDatabaseHas('modules', ['project_id' => $project->id, 'status' => 'generated']);
    }

    public function test_reaper_releases_a_stuck_run_reservation_once(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $sub = $this->subscription($user);
        $project = $this->project($user);
        $run = app(GenerationCredits::class)->enqueue($project, []);
        Module::where('project_id', $project->id)->update(['updated_at' => now()->subHour()]);

        $this->artisan('generation:reap-stuck')->assertSuccessful();
        $this->artisan('generation:reap-stuck')->assertSuccessful();

        $this->assertSame('failed', GenerationRun::find($run->id)->status);
        $this->assertSame(0, $sub->fresh()->modules_reserved);
        $this->assertSame(1, $sub->fresh()->modulesAvailable());
        $this->assertDatabaseHas('modules', ['project_id' => $project->id, 'status' => 'failed']);
    }
}
