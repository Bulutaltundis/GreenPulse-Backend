<?php

namespace Tests\Feature;

use App\Models\Neighborhood;
use App\Models\Observation;
use App\Models\ObservationReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    private function createModerator(string $role = 'moderator'): User
    {
        $user = User::factory()->create();

        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    public function test_guest_cannot_access_moderation_queue(): void
    {
        $this->getJson('/api/v1/moderation/observations')
            ->assertUnauthorized();
    }

    public function test_regular_user_cannot_access_moderation_queue(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/moderation/observations')
            ->assertForbidden();
    }

    public function test_moderator_can_list_only_pending_observations(): void
    {
        $moderator = $this->createModerator();

        Observation::factory()->create([
            'status' => Observation::STATUS_PENDING,
        ]);

        Observation::factory()->create([
            'status' => Observation::STATUS_APPROVED,
        ]);

        Observation::factory()->create([
            'status' => Observation::STATUS_REJECTED,
        ]);

        $this->actingAs($moderator, 'sanctum')
            ->getJson('/api/v1/moderation/observations')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_moderator_can_view_pending_observation_detail(): void
    {
        $moderator = $this->createModerator();

        $observation = Observation::factory()->create([
            'status' => Observation::STATUS_PENDING,
        ]);

        $this->actingAs($moderator, 'sanctum')
            ->getJson("/api/v1/moderation/observations/{$observation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $observation->id)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_moderator_can_approve_observation(): void
    {
        $moderator = $this->createModerator();

        $observation = Observation::factory()->create([
            'status' => Observation::STATUS_PENDING,
        ]);

        $this->actingAs($moderator, 'sanctum')
            ->postJson(
                "/api/v1/moderation/observations/{$observation->id}/approve",
                [
                    'impact_score' => -3,
                    'reason' => 'Gözlem doğrulandı.',
                ]
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('observations', [
            'id' => $observation->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('observation_reviews', [
            'observation_id' => $observation->id,
            'moderator_id' => $moderator->id,
            'previous_status' => 'pending',
            'new_status' => 'approved',
            'reason' => 'Gözlem doğrulandı.',
        ]);
    }

    public function test_moderator_can_reject_observation_with_reason(): void
    {
        $moderator = $this->createModerator();

        $observation = Observation::factory()->create([
            'status' => Observation::STATUS_PENDING,
        ]);

        $this->actingAs($moderator, 'sanctum')
            ->postJson(
                "/api/v1/moderation/observations/{$observation->id}/reject",
                ['reason' => 'Yeterli kanıt bulunamadı.']
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('observation_reviews', [
            'observation_id' => $observation->id,
            'moderator_id' => $moderator->id,
            'previous_status' => 'pending',
            'new_status' => 'rejected',
            'reason' => 'Yeterli kanıt bulunamadı.',
        ]);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $moderator = $this->createModerator();

        $observation = Observation::factory()->create([
            'status' => Observation::STATUS_PENDING,
        ]);

        $this->actingAs($moderator, 'sanctum')
            ->postJson(
                "/api/v1/moderation/observations/{$observation->id}/reject",
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_observation_cannot_be_reviewed_twice(): void
    {
        $moderator = $this->createModerator();

        $observation = Observation::factory()->create([
            'status' => Observation::STATUS_PENDING,
        ]);

        $this->actingAs($moderator, 'sanctum')
            ->postJson(
                "/api/v1/moderation/observations/{$observation->id}/approve",
                ['impact_score' => 2]
            )
            ->assertOk();

        $this->postJson(
            "/api/v1/moderation/observations/{$observation->id}/reject",
            ['reason' => 'Tekrar değerlendirme.']
        )->assertStatus(409);

        $this->assertDatabaseCount('observation_reviews', 1);
    }

    public function test_admin_can_access_moderation_endpoints(): void
    {
        $admin = $this->createModerator('admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/moderation/observations')
            ->assertOk();
    }

    public function test_registration_cannot_assign_moderator_role(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'role-test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role' => 'admin',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'role-test@example.com',
            'role' => 'user',
        ]);
    }
}
