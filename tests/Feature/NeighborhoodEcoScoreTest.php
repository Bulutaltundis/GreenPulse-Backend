<?php

namespace Tests\Feature;

use App\Models\EcoScoreSnapshot;
use App\Models\Neighborhood;
use App\Models\Observation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NeighborhoodEcoScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_neighborhood_without_scored_observations_has_no_eco_score(): void
    {
        $neighborhood = Neighborhood::factory()->create();

        $response = $this->getJson(
            "/api/v1/neighborhoods/{$neighborhood->id}/eco-score"
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.score', null)
            ->assertJsonPath('data.has_data', false)
            ->assertJsonPath('data.observation_count', 0)
            ->assertJsonPath('data.average_impact', null);

        $this->assertDatabaseCount('eco_score_snapshots', 0);
    }

    public function test_eco_score_uses_only_approved_observations_with_impact_scores(): void
    {
        $neighborhood = Neighborhood::factory()->create();

        // Approved: -2
        $negative = Observation::factory()->create([
            'neighborhood_id' => $neighborhood->id,
            'status' => Observation::STATUS_APPROVED,
        ]);

        $negative->forceFill(['impact_score' => -2])->save();

        // Approved: +4
        $positive = Observation::factory()->create([
            'neighborhood_id' => $neighborhood->id,
            'status' => Observation::STATUS_APPROVED,
        ]);

        $positive->forceFill(['impact_score' => 4])->save();

        // Pending observations must not affect the score.
        $pending = Observation::factory()->create([
            'neighborhood_id' => $neighborhood->id,
            'status' => Observation::STATUS_PENDING,
        ]);

        $pending->forceFill(['impact_score' => 5])->save();

        // Approved observations without an impact score must not count.
        Observation::factory()->create([
            'neighborhood_id' => $neighborhood->id,
            'status' => Observation::STATUS_APPROVED,
        ]);

        // Average impact: (-2 + 4) / 2 = 1
        // Eco Score: 50 + (1 * 10) = 60

        $response = $this->getJson(
            "/api/v1/neighborhoods/{$neighborhood->id}/eco-score"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.score', 60)
            ->assertJsonPath('data.has_data', true)
            ->assertJsonPath('data.observation_count', 2)
            ->assertJsonPath('data.average_impact', '1.00');
    }

    public function test_recalculating_unchanged_eco_score_does_not_create_duplicate_snapshot(): void
    {
        $neighborhood = Neighborhood::factory()->create();

        $observation = Observation::factory()->create([
            'neighborhood_id' => $neighborhood->id,
            'status' => Observation::STATUS_APPROVED,
        ]);

        $observation->forceFill(['impact_score' => 3])->save();

        $url = "/api/v1/neighborhoods/{$neighborhood->id}/eco-score";

        $this->getJson($url)->assertOk();
        $this->getJson($url)->assertOk();

        $this->assertDatabaseCount('eco_score_snapshots', 1);

        $this->assertDatabaseHas('eco_score_snapshots', [
            'neighborhood_id' => $neighborhood->id,
            'score' => 80,
            'observation_count' => 1,
        ]);
    }

    public function test_inactive_neighborhood_eco_score_returns_404(): void
    {
        $neighborhood = Neighborhood::factory()->create([
            'is_active' => false,
        ]);

        $this->getJson(
            "/api/v1/neighborhoods/{$neighborhood->id}/eco-score"
        )->assertNotFound();
    }
}
