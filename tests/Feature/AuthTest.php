<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@greenpulse.local',
            'password' => 'GreenPulse2026',
            'password_confirmation' => 'GreenPulse2026',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'test@greenpulse.local')
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                    'token_type',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@greenpulse.local',
        ]);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'existing@greenpulse.local',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Another User',
            'email' => 'existing@greenpulse.local',
            'password' => 'GreenPulse2026',
            'password_confirmation' => 'GreenPulse2026',
        ]);

        $response->assertUnprocessable();
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'login@greenpulse.local',
            'password' => 'GreenPulse2026',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@greenpulse.local',
            'password' => 'GreenPulse2026',
            'device_name' => 'Feature Test',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'login@greenpulse.local')
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@greenpulse.local',
            'password' => 'GreenPulse2026',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@greenpulse.local',
            'password' => 'WrongPassword2026',
        ]);

        $response->assertUnprocessable();
    }

    public function test_authenticated_user_can_access_me(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_guest_cannot_access_me(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }


public function test_user_can_logout_and_token_is_revoked(): void
{
    $user = User::factory()->create();

    $newToken = $user->createToken('feature-test');
    $token = $newToken->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('success', true);

    // Token gerçekten veritabanından silindi mi?
    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $newToken->accessToken->id,
    ]);

    // Test uygulamasındaki önceki kimlik doğrulama durumunu temizle.
    $this->app['auth']->forgetGuards();

    // İptal edilen token artık erişim sağlayamamalı.
    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
}

}
