<?php

namespace Tests\Feature;

use App\Models\Observation;
use App\Models\ObservationAnalysis;
use App\Models\ObservationMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObservationAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function createAnalysis(
        Observation $observation,
        array $attributes = []
    ): ObservationAnalysis {
        $media = ObservationMedia::create([
            'observation_id' => $observation->id,
            'disk' => 'private',
            'path' => 'observations/test/photo.jpg',
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);

        return ObservationAnalysis::create(array_merge([
            'observation_id' => $observation->id,
            'observation_media_id' => $media->id,
            'status' => ObservationAnalysis::STATUS_COMPLETED,
            'provider' => 'gemini',
            'model' => 'gemini-3.5-flash-lite',
            'summary' => 'Fotoğrafta plastik atıklar tespit edildi.',
            'detected_category' => 'waste',
            'environmental_issue' => true,
            'confidence' => 94.50,
            'findings' => ['Plastik atık'],
            'recommendations' => ['Atıklar geri dönüşüme gönderilmeli.'],
            'error_code' => 'INTERNAL_TEST_ERROR',
            'error_message' => 'Internal error details must not be exposed.',
            'completed_at' => now(),
        ], $attributes));
    }

    public function test_guest_can_view_approved_observation_analyses(): void
    {
        $observation = Observation::factory()->create([
            'status' => Observation::STATUS_APPROVED,
        ]);

        $analysis = $this->createAnalysis($observation);

        $this->getJson(
            "/api/v1/observations/{$observation->id}/analyses"
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $analysis->id)
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.detected_category', 'waste');
    }

    public function test_owner_can_view_own_pending_observation_analyses(): void
    {
        $user = User::factory()->create();

        $observation = Observation::factory()->create([
            'user_id' => $user->id,
            'status' => Observation::STATUS_PENDING,
        ]);

        $analysis = $this->createAnalysis($observation);

        $this->actingAs($user, 'sanctum')
            ->getJson(
                "/api/v1/observations/{$observation->id}/analyses"
            )
            ->assertOk()
            ->assertJsonPath('data.0.id', $analysis->id);
    }

    public function test_user_cannot_view_another_users_pending_analyses(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $observation = Observation::factory()->create([
            'user_id' => $owner->id,
            'status' => Observation::STATUS_PENDING,
        ]);

        $this->createAnalysis($observation);

        $this->actingAs($otherUser, 'sanctum')
            ->getJson(
                "/api/v1/observations/{$observation->id}/analyses"
            )
            ->assertNotFound();
    }

    public function test_moderator_can_view_pending_observation_analyses(): void
    {
        $moderator = User::factory()->create([
            'role' => 'moderator',
        ]);

        $owner = User::factory()->create();

        $observation = Observation::factory()->create([
            'user_id' => $owner->id,
            'status' => Observation::STATUS_PENDING,
        ]);

        $analysis = $this->createAnalysis($observation);

        $this->actingAs($moderator, 'sanctum')
            ->getJson(
                "/api/v1/observations/{$observation->id}/analyses"
            )
            ->assertOk()
            ->assertJsonPath('data.0.id', $analysis->id);
    }

    public function test_analysis_api_does_not_expose_internal_error_details(): void
    {
        $observation = Observation::factory()->create([
            'status' => Observation::STATUS_APPROVED,
        ]);

        $this->createAnalysis($observation);

        $response = $this->getJson(
            "/api/v1/observations/{$observation->id}/analyses"
        );

        $response->assertOk()
            ->assertJsonMissingPath('data.0.error_code')
            ->assertJsonMissingPath('data.0.error_message')
            ->assertJsonMissingPath('data.0.provider')
            ->assertJsonMissingPath('data.0.model');

        $this->assertStringNotContainsString(
            'Internal error details',
            $response->getContent()
        );
    }
}
