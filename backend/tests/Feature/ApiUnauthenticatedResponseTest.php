<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * There is no `login` named route in this API-only backend, so the framework's
 * default unauthenticated handling tried to redirect to it and raised
 * "Route [login] not defined" - a 500 where a 401 belongs. Browsers and any
 * client that does not send Accept: application/json hit this.
 */
class ApiUnauthenticatedResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_401_json_without_a_json_accept_header(): void
    {
        $this->get('/api/projects')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_it_returns_401_json_with_a_json_accept_header(): void
    {
        $this->getJson('/api/projects')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }
}
