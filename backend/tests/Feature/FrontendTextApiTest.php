<?php

namespace Tests\Feature;

use App\Models\FrontendText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class FrontendTextApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_active_texts_for_route_key(): void
    {
        FrontendText::create([
            'route_key' => 'projects.list',
            'text_key' => 'projects.title',
            'value' => 'Projects',
            'is_active' => true,
        ]);

        FrontendText::create([
            'route_key' => 'projects.list',
            'text_key' => 'projects.empty_state',
            'value' => 'No projects yet.',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/frontend-texts?route_key=projects.list');

        $response->assertOk()->assertJson([
            'route_key' => 'projects.list',
            'texts' => [
                'projects.empty_state' => 'No projects yet.',
                'projects.title' => 'Projects',
            ],
        ]);
    }

    public function test_it_excludes_inactive_texts_by_default(): void
    {
        FrontendText::create([
            'route_key' => 'projects.list',
            'text_key' => 'projects.title',
            'value' => 'Projects',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/frontend-texts?route_key=projects.list');

        $response->assertOk()->assertJson([
            'route_key' => 'projects.list',
            'texts' => [],
        ]);
    }

    public function test_it_can_include_inactive_texts_when_requested(): void
    {
        FrontendText::create([
            'route_key' => 'projects.list',
            'text_key' => 'projects.title',
            'value' => 'Projects',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/frontend-texts?route_key=projects.list&include_inactive=1');

        $response->assertOk()->assertJson([
            'route_key' => 'projects.list',
            'texts' => [
                'projects.title' => 'Projects',
            ],
        ]);
    }

    public function test_it_returns_empty_map_for_unknown_route(): void
    {
        $response = $this->getJson('/api/frontend-texts?route_key=unknown.route');

        $response->assertOk()->assertJson([
            'route_key' => 'unknown.route',
            'texts' => [],
        ]);
    }

    public function test_it_requires_valid_route_key(): void
    {
        $missing = $this->getJson('/api/frontend-texts');
        $missing->assertStatus(422)->assertJsonValidationErrors(['route_key']);

        $invalid = $this->getJson('/api/frontend-texts?route_key=Bad Route Key');
        $invalid->assertStatus(422)->assertJsonValidationErrors(['route_key']);
    }

    public function test_api_routes_are_registered_under_api_prefix(): void
    {
        $response = $this->getJson('/api/projects');

        $response->assertOk()->assertJson(['projects' => []]);
    }

    public function test_route_key_and_text_key_pair_must_be_unique(): void
    {
        FrontendText::create([
            'route_key' => 'projects.list',
            'text_key' => 'projects.title',
            'value' => 'Projects',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        FrontendText::create([
            'route_key' => 'projects.list',
            'text_key' => 'projects.title',
            'value' => 'Duplicate',
            'is_active' => true,
        ]);
    }
}
