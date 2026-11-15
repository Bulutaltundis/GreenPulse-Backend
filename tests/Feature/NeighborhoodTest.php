<?php

namespace Tests\Feature;

use App\Models\Neighborhood;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NeighborhoodTest extends TestCase
{
    use RefreshDatabase;

    public function test_neighborhoods_can_be_listed(): void
    {
        Neighborhood::factory()->create([
            'name' => 'Test Mahallesi',
            'city' => 'Ankara',
            'district' => 'Çankaya',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/neighborhoods');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'Test Mahallesi')
            ->assertJsonPath('data.0.city', 'Ankara')
            ->assertJsonPath('data.0.area_type', 'neighborhood')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'buildings' => [
                            'before_1998',
                            'under_1998_code',
                            'before_2007',
                            'under_2007_code',
                            'under_2018_code',
                            'total',
                        ],
                    ],
                ],
            ]);
    }

    public function test_neighborhoods_can_be_filtered(): void
    {
        Neighborhood::factory()->create([
            'name' => 'Çankaya Mahallesi',
            'city' => 'Ankara',
            'district' => 'Çankaya',
        ]);

        Neighborhood::factory()->create([
            'name' => 'Yenimahalle Merkezi',
            'city' => 'Ankara',
            'district' => 'Yenimahalle',
        ]);

        $this->getJson(
            '/api/v1/neighborhoods?' . http_build_query([
                'city' => 'Ankara',
                'district' => 'Çankaya',
            ])
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Çankaya Mahallesi');
    }

    public function test_neighborhoods_can_be_searched(): void
    {
        Neighborhood::factory()->create([
            'name' => 'Kızılay Mahallesi',
        ]);

        Neighborhood::factory()->create([
            'name' => 'Bahçelievler Mahallesi',
        ]);

        $this->getJson('/api/v1/neighborhoods?q=Kızılay')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kızılay Mahallesi');
    }

    public function test_neighborhood_details_can_be_viewed(): void
    {
        $neighborhood = Neighborhood::factory()->create([
            'name' => 'Test Mahallesi',
        ]);

        $this->getJson("/api/v1/neighborhoods/{$neighborhood->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $neighborhood->id)
            ->assertJsonPath('data.name', 'Test Mahallesi');
    }

    public function test_nearby_search_requires_coordinates(): void
    {
        $this->getJson('/api/v1/neighborhoods/nearby')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_nearby_search_rejects_invalid_coordinates(): void
    {
        $this->getJson(
            '/api/v1/neighborhoods/nearby?lat=91&lng=181'
        )
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_inactive_neighborhoods_are_not_visible(): void
    {
        $neighborhood = Neighborhood::factory()->create([
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/neighborhoods')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/neighborhoods/{$neighborhood->id}")
            ->assertNotFound();
    }

    public function test_pagination_respects_per_page_parameter(): void
    {
        Neighborhood::factory()->count(3)->create();

        $this->getJson('/api/v1/neighborhoods?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_geometry_endpoint_requires_postgis(): void
    {
        $this->getJson('/api/v1/neighborhoods/1/geometry')
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'GeoJSON geometry requires PostgreSQL with PostGIS.'
            );
    }
}