<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontendTest extends TestCase
{
    private string $index;

    protected function setUp(): void
    {
        parent::setUp();

        $this->index = tempnam(sys_get_temp_dir(), 'spa');
        file_put_contents($this->index, '<!doctype html><div id="root"></div>');
        config(['app.frontend_index' => $this->index]);
    }

    protected function tearDown(): void
    {
        @unlink($this->index);

        parent::tearDown();
    }

    public function test_frontend_is_served_for_spa_routes(): void
    {
        foreach (['/', '/facturas'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
                ->assertSee('<div id="root"></div>', false);
        }
    }

    public function test_api_and_health_routes_are_not_replaced_by_frontend(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonPath('service', 'boletas-api');
        $this->get('/api/missing')->assertNotFound()->assertHeader('Content-Type', 'application/json');
        $this->get('/up')->assertOk()->assertDontSee('<div id="root"></div>', false);
    }
}
