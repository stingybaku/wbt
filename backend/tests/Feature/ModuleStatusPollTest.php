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
 * The editor polls module status every 3 seconds while a module generates.
 * ?fields=status keeps module_json - an entire generated course - out of both
 * the query and the response.
 */
class ModuleStatusPollTest extends TestCase
{
    use RefreshDatabase;

    private function actAsOwnerWithModule(string $status = 'generating'): Project
    {
        $user = User::factory()->create();

        $project = Project::create([
            'id'                 => 'proj_poll',
            'title'              => 'Poll project',
            'rights_attestation' => true,
            'user_id'            => $user->id,
        ]);

        Module::create([
            'id'          => 'mod_poll',
            'project_id'  => $project->id,
            'status'      => $status,
            'module_json' => ['meta' => ['title' => 'Course'], 'screens' => [['id' => 's1']]],
        ]);

        Sanctum::actingAs($user);

        return $project;
    }

    public function test_status_mode_omits_the_course_document(): void
    {
        $project = $this->actAsOwnerWithModule();

        $response = $this->getJson("/api/projects/{$project->id}/module?fields=status")
            ->assertOk()
            ->assertJsonPath('module.id', 'mod_poll')
            ->assertJsonPath('module.status', 'generating');

        $this->assertArrayNotHasKey('module_json', $response->json('module'));
    }

    public function test_status_mode_does_not_even_select_the_column(): void
    {
        $project = $this->actAsOwnerWithModule();

        DB::enableQueryLog();
        $this->getJson("/api/projects/{$project->id}/module?fields=status")->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $moduleQuery = collect($queries)->first(fn ($q) => str_contains($q['query'], 'from "modules"'));

        $this->assertNotNull($moduleQuery);
        $this->assertStringNotContainsString('"module_json"', $moduleQuery['query']);
    }

    public function test_the_default_response_still_carries_the_course(): void
    {
        $project = $this->actAsOwnerWithModule('generated');

        $this->getJson("/api/projects/{$project->id}/module")
            ->assertOk()
            ->assertJsonPath('module.status', 'generated')
            ->assertJsonPath('module.module_json.meta.title', 'Course');
    }

    public function test_status_mode_still_requires_ownership(): void
    {
        $project = $this->actAsOwnerWithModule();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/projects/{$project->id}/module?fields=status")
            ->assertNotFound();
    }
}
