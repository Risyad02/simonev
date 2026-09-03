<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_standard_success_response(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'API SIMONEV berjalan normal',
            ])
            ->assertJsonStructure([
                'success',
                'data' => ['status', 'timestamp', 'checked_by'],
                'message',
            ]);
    }

    public function test_unknown_api_route_returns_standard_error_response(): void
    {
        $response = $this->getJson('/api/v1/rute-tidak-ada');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ])
            ->assertJsonStructure([
                'success',
                'errors',
                'message',
            ]);
    }
}