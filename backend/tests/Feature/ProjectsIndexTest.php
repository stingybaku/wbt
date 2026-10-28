<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The dashboard's summary fields are extracted from module_json in SQL rather
 * than by loading the whole document, so these pin the response shape - the
 * frontend types source_summary as string|null and both counters as
 * number|null, and checks them with !== null.
 */
class ProjectsIndexTest extends TestCase
{
    use RefreshDatabase;

    private function project(User $owner, string $id, ?array $moduleJson, string $status = 'generated'): Project
    {
        $project = Project::create([
            'id'                 => $id,
            'title'              => 'Project ' . $id,
            'rights_attestation' => true,
            'user_id'            => $owner->id,
        ]);

        Module::create([
            'id'              => 'mod_' . $id,
            'project_id'      => $project->id,
            'status'          => $status,
            'module_json'     => $moduleJson,
            'cover_image_url' => 'https://example.com/cover.jpg',
        ]);

        return $project;
    }

    public function test_it_returns_summary_fields_extracted_from_module_json(): void
    {
        $user = User::factory()->create();
        $this->project($user, 'a', [
            'meta'    => ['summary' => 'A course about safety', 'duration_minutes' => 20],
            'screens' => [['id' => 's1'], ['id' => 's2'], ['id' => 's3']],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/projects')->assertOk();

        $response->assertJsonPath('projects.0.source_summary', 'A course about safety');
        $response->assertJsonPath('projects.0.duration_minutes', 20);
        $response->assertJsonPath('projects.0.screen_count', 3);
        $response->assertJsonPath('projects.0.module_status', 'generated');
        $response->assertJsonPath('projects.0.cover_image_url', 'https://example.com/cover.jpg');

        // Numeric fields must be numbers, not the strings JSON extraction can yield.
        $this->assertIsInt($response->json('projects.0.duration_minutes'));
        $this->assertIsInt($response->json('projects.0.screen_count'));
    }

    public function test_it_falls_back_to_source_summary_key(): void
    {
        $user = User::factory()->create();
        $this->project($user, 'b', [
            'meta'    => ['source_summary' => 'Fallback summary'],
            'screens' => [],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonPath('projects.0.source_summary', 'Fallback summary')
            ->assertJsonPath('projects.0.screen_count', 0)
            ->assertJsonPath('projects.0.duration_minutes', null);
    }

    public function test_it_returns_nulls_for_a_module_that_has_not_generated_yet(): void
    {
        $user = User::factory()->create();
        $this->project($user, 'c', null, 'generating');

        Sanctum::actingAs($user);

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonPath('projects.0.module_status', 'generating')
            ->assertJsonPath('projects.0.source_summary', null)
            ->assertJsonPath('projects.0.duration_minutes', null)
            ->assertJsonPath('projects.0.screen_count', null);
    }

    public function test_it_does_not_select_the_module_json_document(): void
    {
        $user = User::factory()->create();
        $this->project($user, 'd', [
            'meta'    => ['summary' => 'x'],
            'screens' => [['id' => 's1']],
        ]);

        Sanctum::actingAs($user);

        DB::enableQueryLog();
        $this->getJson('/api/projects')->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $moduleQuery = collect($queries)->first(fn ($q) => str_contains($q['query'], 'from "modules"'));

        $this->assertNotNull($moduleQuery, 'Expected the modules relation to be eager loaded.');
        $this->assertStringNotContainsString(
            '"module_json"',
            $moduleQuery['query'],
            'module_json must not be selected as a column; only JSON paths out of it.'
        );
    }

    public function test_it_only_returns_the_callers_own_projects(): void
    {
        $owner = User::factory()->create();
        $this->project($owner, 'mine', ['meta' => [], 'screens' => []]);

        $other = User::factory()->create();
        $this->project($other, 'theirs', ['meta' => [], 'screens' => []]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/projects')->assertOk();

        $this->assertCount(1, $response->json('projects'));
        $this->assertSame('mine', $response->json('projects.0.id'));
    }
}
