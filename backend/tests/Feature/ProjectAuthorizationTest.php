<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fixtures;
use Tests\TestCase;

class ProjectAuthorizationTest extends TestCase
{
    use Fixtures, RefreshDatabase;

    public function test_project_endpoints_require_authentication_and_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = $this->project($owner);
        $this->course($project);
        $base = '/api/projects/'.$project->id;
        $endpoints = [
            ['GET', "$base/module", []],
            ['POST', "$base/events", ['type' => 'test']],
            ['POST', "$base/module/flags", ['target_id' => 'intro', 'target_type' => 'screen', 'reason' => 'test']],
        ];
        foreach ($endpoints as [$method, $url, $body]) {
            $this->json($method, $url, $body)->assertUnauthorized();
        }
        $this->actingAs($other);
        foreach ($endpoints as [$method, $url, $body]) {
            $this->json($method, $url, $body)->assertNotFound();
        }
        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('module_flags', 0);
        $this->actingAs($owner);
        foreach ($endpoints as [$method, $url, $body]) {
            $this->json($method, $url, $body)->assertSuccessful();
        }
    }
}
