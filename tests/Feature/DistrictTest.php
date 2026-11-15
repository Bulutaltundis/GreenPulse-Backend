<?php

namespace Tests\Feature;

use App\Models\Neighborhood;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistrictTest extends TestCase
{
    use RefreshDatabase;

    public function test_districts_can_be_listed(): void
    {
        Neighborhood::factory()->create([
            'name' => 'Test Mahallesi 1',
            'district' => 'Çankaya',
            'source' => 'ankara_bb_deprem_gis',
            'area_type' => 'neighborhood',
        ]);

        Neighborhood::factory()->create([
            'name' => 'Test Mahallesi 2',
            'district' => 'Çankaya',
            'source' => 'ankara_bb_deprem_gis',
            'area_type' => 'neighborhood',
        ]);

        Neighborhood::factory()->create([
            'name' => 'Test OSB',
            'district' => 'Çankaya',
            'source' => 'ankara_bb_deprem_gis',
            'area_type' => 'industrial_zone',
        ]);

        $this->getJson('/api/v1/districts')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.district', 'Çankaya')
            ->assertJsonPath('data.0.neighborhood_count', 2)
            ->assertJsonPath('data.0.industrial_zone_count', 1);
    }

    public function test_inactive_and_external_records_are_excluded(): void
    {
        Neighborhood::factory()->create([
            'district' => 'Çankaya',
            'source' => 'ankara_bb_deprem_gis',
            'area_type' => 'neighborhood',
            'is_active' => true,
        ]);

        Neighborhood::factory()->create([
            'district' => 'Çankaya',
            'source' => 'ankara_bb_deprem_gis',
            'area_type' => 'neighborhood',
            'is_active' => false,
        ]);

        Neighborhood::factory()->create([
            'district' => 'Çankaya',
            'source' => 'another_source',
            'area_type' => 'neighborhood',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/districts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.neighborhood_count', 1);
    }

    public function test_district_geometry_requires_postgis(): void
    {
        $this->getJson('/api/v1/districts/CANKAYA/geometry')
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'District geometry requires PostgreSQL with PostGIS.'
            );
    }
}
