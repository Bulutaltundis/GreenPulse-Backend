<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DistrictController extends Controller
{
    public function index(): JsonResponse
    {
        $districts = Cache::remember(
            'greenpulse:districts:v1',
            now()->addHours(12),
            function () {
                return DB::table('neighborhoods')
                    ->where('source', 'ankara_bb_deprem_gis')
                    ->where('is_active', true)
                    ->select('city', 'district')
                    ->selectRaw("
                        SUM(CASE
                            WHEN area_type = 'neighborhood' THEN 1
                            ELSE 0
                        END) AS neighborhood_count
                    ")
                    ->selectRaw("
                        SUM(CASE
                            WHEN area_type = 'industrial_zone' THEN 1
                            ELSE 0
                        END) AS industrial_zone_count
                    ")
                    ->groupBy('city', 'district')
                    ->orderBy('district')
                    ->get();
            }
        );

        return response()->json([
            'success' => true,
            'data' => $districts,
            'meta' => [
                'count' => $districts->count(),
            ],
        ]);
    }

    public function geometry(string $district): JsonResponse
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return response()->json([
                'success' => false,
                'message' => 'District geometry requires PostgreSQL with PostGIS.',
            ], 503);
        }

        $districtRecord = DB::table('neighborhoods')
            ->where('source', 'ankara_bb_deprem_gis')
            ->where('is_active', true)
            ->where('district', $district)
            ->select('city', 'district')
            ->first();

        if (! $districtRecord) {
            return response()->json([
                'success' => false,
                'message' => 'District not found.',
            ], 404);
        }

        $geometry = Cache::remember(
            'greenpulse:district-geometry:v1:' . mb_strtolower($district),
            now()->addHours(12),
            function () use ($districtRecord) {
                $result = DB::selectOne(
                    "SELECT ST_AsGeoJSON(
                        ST_Multi(
                            ST_CollectionExtract(
                                ST_Union(boundary),
                                3
                            )
                        ),
                        6
                    ) AS geometry
                    FROM neighborhoods
                    WHERE source = ?
                    AND is_active = true
                    AND district = ?
                    AND boundary IS NOT NULL",
                    [
                        'ankara_bb_deprem_gis',
                        $districtRecord->district,
                    ]
                );

                return $result?->geometry;
            }
        );

        if (! $geometry) {
            return response()->json([
                'success' => false,
                'message' => 'District geometry not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'Feature',
                'geometry' => json_decode($geometry, true),
                'properties' => [
                    'city' => $districtRecord->city,
                    'district' => $districtRecord->district,
                ],
            ],
        ]);
    }
}
