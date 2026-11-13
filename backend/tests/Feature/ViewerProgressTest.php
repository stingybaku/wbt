<?php

namespace Tests\Feature;

use App\Jobs\GenerateCertificateJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Fixtures;
use Tests\TestCase;

class ViewerProgressTest extends TestCase
{
    use Fixtures, RefreshDatabase;

    public function test_fabricated_progress_and_answers_are_rejected(): void
    {
        Queue::fake();
        $project = $this->project(User::factory()->create());
        $this->course($project);
        $invite = $this->invite($project);
        $url = "/api/view/{$invite->token}/progress";
        $this->postJson($url, ['current_screen_index' => 0, 'total_screens' => 1, 'complete' => true])->assertUnprocessable();
        $this->postJson($url, ['current_screen_index' => 2, 'total_screens' => 3, 'complete' => true])->assertUnprocessable();
        $this->postJson($url, ['current_screen_index' => 1, 'visited_screens' => [0, 1, 2]])->assertUnprocessable();
        $this->postJson($url, ['current_screen_index' => 0, 'question_answers' => [
            'unknown' => ['selected' => ['safe'], 'submitted' => true, 'isCorrect' => true],
        ]])->assertUnprocessable();
        $this->assertNull($invite->fresh()->progress->completed_at);
        Queue::assertNothingPushed();
    }

    public function test_completion_requires_questions_and_is_idempotent_and_server_scored(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $project = $this->project($user);
        $this->course($project);
        $invite = $this->invite($project);
        $url = "/api/view/{$invite->token}/progress";
        $this->postJson($url, ['current_screen_index' => 1])->assertOk();
        $this->postJson($url, ['current_screen_index' => 2])->assertUnprocessable();
        $this->postJson($url, ['current_screen_index' => 1, 'question_answers' => [
            'quiz' => ['selected' => ['unsafe'], 'submitted' => true, 'isCorrect' => true],
        ]])->assertOk();
        $this->assertFalse($invite->fresh()->progress->question_answers['quiz']['isCorrect']);
        $this->postJson($url, ['current_screen_index' => 2])->assertOk();
        $this->assertNull($invite->fresh()->progress->completed_at);
        $this->postJson($url, ['current_screen_index' => 2, 'complete' => true])->assertOk();
        $completedAt = $invite->fresh()->progress->completed_at;
        $this->postJson($url, ['current_screen_index' => 2, 'complete' => true])->assertOk();
        $this->assertEquals($completedAt, $invite->fresh()->progress->completed_at);
        Queue::assertPushed(GenerateCertificateJob::class, 1);
        $this->assertDatabaseCount('events', 1);
        $this->actingAs($user)->getJson("/api/projects/{$project->id}/report")->assertOk()
            ->assertJsonPath('viewers.0.score.correct', 0)->assertJsonPath('viewers.0.score.total', 1);
    }

    public function test_report_regrades_legacy_answers_and_ignores_unknown_screens(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);
        $this->course($project);
        $invite = $this->invite($project);
        $invite->progress->update(['question_answers' => [
            'quiz' => ['selected' => ['unsafe'], 'submitted' => true, 'isCorrect' => true],
            'fake' => ['selected' => ['safe'], 'submitted' => true, 'isCorrect' => true],
        ]]);
        $this->actingAs($user)->getJson("/api/projects/{$project->id}/report")->assertOk()
            ->assertJsonPath('viewers.0.score.correct', 0);
    }
}
