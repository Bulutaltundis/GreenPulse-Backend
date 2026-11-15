<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiErrorTest extends TestCase
{
    public function test_api_validation_errors_use_standard_format(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['name', 'email', 'password'],
            ]);
    }

    public function test_api_authentication_errors_use_standard_format(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_unknown_api_routes_use_standard_format(): void
    {
        $this->getJson('/api/v1/nonexistent')
            ->assertNotFound()
            ->assertExactJson([
                'success' => false,
                'message' => 'The requested resource was not found.',
            ]);
    }
}
