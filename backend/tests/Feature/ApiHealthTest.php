<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    public function test_health_does_not_require_a_database_connection(): void
    {
        config(['database.default' => 'unconfigured']);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'boletas-api',
            ]);
    }

    public function test_configured_frontend_origin_is_allowed(): void
    {
        $origin = config('cors.allowed_origins.0');

        $this->withHeader('Origin', $origin)
            ->getJson('/api/health')
            ->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    public function test_unknown_origin_is_not_allowed(): void
    {
        $this->withHeader('Origin', 'https://untrusted.example')
            ->getJson('/api/health')
            ->assertHeader('Access-Control-Allow-Origin', config('cors.allowed_origins.0'));
    }

    public function test_unknown_api_routes_return_json(): void
    {
        $this->get('/api/missing')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }
}
