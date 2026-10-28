<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModuleOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeProjectWithModule(User $owner): Project
    {
        $project = Project::create([
            'id'                 => 'proj_test-ownership',
            'title'              => 'Owner project',
            'rights_attestation' => true,
            'user_id'            => $owner->id,
        ]);

        Module::create([
            'id'          => 'mod_test-ownership',
            'project_id'  => $project->id,
            'status'      => 'generated',
            'module_json' => ['meta' => ['title' => 'Secret course'], 'screens' => []],
        ]);

        return $project;
    }

    public function test_owner_can_fetch_their_module(): void
    {
        $owner = User::factory()->create();
        $project = $this->makeProjectWithModule($owner);

        Sanctum::actingAs($owner);

        $this->getJson("/api/projects/{$project->id}/module")
            ->assertOk()
            ->assertJsonPath('module.id', 'mod_test-ownership');
    }

    public function test_other_users_cannot_fetch_someone_elses_module(): void
    {
        $owner = User::factory()->create();
        $project = $this->makeProjectWithModule($owner);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/projects/{$project->id}/module")
            ->assertNotFound();
    }
}
