<?php

namespace Tests\Feature;

use App\Models\Neighborhood;
use App\Models\Observation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_observation(): void
    {
        $this->postJson('/api/v1/observations', [])
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_observation(): void
    {
        $user = User::factory()->create();
        $neighborhood = Neighborhood::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/observations', [
                'neighborhood_id' => $neighborhood->id,
                'category' => 'waste',
                'description' => 'Mahalle parkında plastik atıklar bulunuyor.',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.category', 'waste');

        $this->assertDatabaseHas('observations', [
            'user_id' => $user->id,
            'neighborhood_id' => $neighborhood->id,
            'category' => 'waste',
            'status' => 'pending',
        ]);
    }

    public function test_observation_creation_rejects_invalid_category(): void
    {
        $user = User::factory()->create();
        $neighborhood = Neighborhood::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/observations', [
                'neighborhood_id' => $neighborhood->id,
                'category' => 'invalid_category',
                'description' => 'Bu açıklama yeterince uzun.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    public function test_public_list_only_contains_approved_observations(): void
    {
        Observation::factory()->create([
            'status' => Observation::STATUS_APPROVED,
        ]);

        Observation::factory()->create([
            'status' => Observation::STATUS_PENDING,
        ]);

        Observation::factory()->create([
            'status' => Observation::STATUS_REJECTED,
        ]);

        $this->getJson('/api/v1/observations')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'approved');
    }

    public function test_owner_can_view_own_pending_observation(): void
    {
        $user = User::factory()->create();

        $observation = Observation::factory()->create([
            'user_id' => $user->id,
            'status' => Observation::STATUS_PENDING,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/observations/{$observation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $observation->id)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_user_cannot_view_another_users_pending_observation(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $observation = Observation::factory()->create([
            'user_id' => $owner->id,
            'status' => Observation::STATUS_PENDING,
        ]);

        $this->actingAs($otherUser, 'sanctum')
            ->getJson("/api/v1/observations/{$observation->id}")
            ->assertNotFound();
    }

    public function test_user_can_list_own_observations(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Observation::factory()->count(2)->create([
            'user_id' => $user->id,
        ]);

        Observation::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/observations')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_delete_own_observation(): void
    {
        $user = User::factory()->create();

        $observation = Observation::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/observations/{$observation->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('observations', [
            'id' => $observation->id,
        ]);
    }

    public function test_user_cannot_delete_another_users_observation(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $observation = Observation::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/v1/observations/{$observation->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('observations', [
            'id' => $observation->id,
            'deleted_at' => null,
        ]);
    }
}
