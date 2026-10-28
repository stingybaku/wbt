<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CORS is handled by the framework's HandleCors middleware, driven by
 * config/cors.php. Without that config file the framework default of
 * allowed_origins => ['*'] applied to every api/* path.
 *
 * HandleCors re-reads config on each request, so overriding it here exercises
 * the real middleware.
 */
class CorsTest extends TestCase
{
    use RefreshDatabase;

    private function allowOrigins(array $origins): void
    {
        config(['cors.allowed_origins' => $origins]);
    }

    public function test_it_allows_a_configured_origin(): void
    {
        $this->allowOrigins(['https://app.example.com']);

        $this->withHeaders(['Origin' => 'https://app.example.com'])
            ->getJson('/api/frontend-texts?route_key=projects.list')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');
    }

    /**
     * The allow-origin header is echoed back as the *configured* origin rather
     * than the caller's, so the browser's own comparison rejects the response.
     * Asserting the header is absent would be wrong; asserting it never names
     * the caller is the property that actually keeps other sites out.
     */
    public function test_it_does_not_echo_an_unconfigured_origin(): void
    {
        $this->allowOrigins(['https://app.example.com']);

        $response = $this->withHeaders(['Origin' => 'https://evil.example.com'])
            ->getJson('/api/frontend-texts?route_key=projects.list');

        $this->assertNotSame(
            'https://evil.example.com',
            $response->headers->get('Access-Control-Allow-Origin'),
            'An unconfigured origin must never be echoed back as allowed.'
        );
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_it_allows_each_origin_in_a_multi_entry_list(): void
    {
        // Mirrors how config/cors.php splits a comma-separated FRONTEND_URL.
        $this->allowOrigins(['https://a.example.com', 'https://b.example.com']);

        foreach (['https://a.example.com', 'https://b.example.com'] as $origin) {
            $this->withHeaders(['Origin' => $origin])
                ->getJson('/api/frontend-texts?route_key=projects.list')
                ->assertHeader('Access-Control-Allow-Origin', $origin);
        }
    }

    public function test_preflight_from_an_allowed_origin_is_accepted(): void
    {
        $this->allowOrigins(['https://app.example.com']);

        $response = $this->withHeaders([
            'Origin'                        => 'https://app.example.com',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/projects');

        $this->assertLessThan(400, $response->getStatusCode());
        $response->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');
    }

    public function test_the_allow_list_is_not_a_wildcard_by_default(): void
    {
        // Regression guard: the framework default was ['*'], which made every
        // api/* response readable by any origin.
        $this->assertNotContains('*', config('cors.allowed_origins'));
    }
}
