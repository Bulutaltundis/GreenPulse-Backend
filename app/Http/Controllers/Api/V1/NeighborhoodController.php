<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\NeighborhoodIndexRequest;
use App\Http\Resources\NeighborhoodResource;
use App\Models\Neighborhood;
use App\Http\Requests\Api\V1\NearbyNeighborhoodRequest;
use Illuminate\Support\Facades\DB;
use App\Services\EcoScoreService;

class NeighborhoodController extends Controller
{
    public function index(NeighborhoodIndexRequest $request)
    {
        $filters = $request->validated();

        $neighborhoods = Neighborhood::query()
            ->where('is_active', true)

            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $term = "%{$search}%";

                    $query
                        ->whereRaw('LOWER(name) LIKE LOWER(?)', [$term])
                        ->orWhereRaw('LOWER(city) LIKE LOWER(?)', [$term])
                        ->orWhereRaw('LOWER(district) LIKE LOWER(?)', [$term]);
                });
            })

            ->when($filters['city'] ?? null, function ($query, $city) {
                $query->where('city', $city);
            })

            ->when($filters['district'] ?? null, function ($query, $district) {
                $query->where('district', $district);
            })

            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return NeighborhoodResource::collection($neighborhoods)
            ->additional([
                'success' => true,
            ]);
    }

    public function show(string $neighborhood)
    {
        $neighborhood = Neighborhood::query()
            ->where('is_active', true)
            ->findOrFail($neighborhood);

        return (new NeighborhoodResource($neighborhood))
            ->additional([
                'success' => true,
            ]);
    }

    public function ecoScore(
        int $neighborhood,
        EcoScoreService $ecoScoreService
    ): \Illuminate\Http\JsonResponse {
        $record = Neighborhood::query()
            ->where('is_active', true)
            ->findOrFail($neighborhood);

        $snapshot = $ecoScoreService->calculateForNeighborhood($record);

        return response()->json([
            'success' => true,
            'data' => [
                'neighborhood_id' => $record->id,
                'neighborhood_name' => $record->name,
                'score' => $snapshot?->score,
                'has_data' => $snapshot !== null,
                'observation_count' => $snapshot?->observation_count ?? 0,
                'average_impact' => $snapshot?->average_impact,
                'calculated_at' => $snapshot?->calculated_at?->toISOString(),
            ],
        ]);
    }

    public function geometry(int $neighborhood): \Illuminate\Http\JsonResponse
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return response()->json([
                'success' => false,
                'message' => 'GeoJSON geometry requires PostgreSQL with PostGIS.',
            ], 503);
        }

        $record = Neighborhood::query()
            ->where('is_active', true)
            ->findOrFail($neighborhood);

        $result = DB::selectOne(
            'SELECT ST_AsGeoJSON(boundary, 6) AS geometry
             FROM neighborhoods
             WHERE id = ? AND boundary IS NOT NULL',
            [$record->id]
        );

        if (! $result || ! $result->geometry) {
            return response()->json([
                'success' => false,
                'message' => 'Neighborhood geometry not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'Feature',
                'geometry' => json_decode($result->geometry, true),
                'properties' => [
                    'id' => $record->id,
                    'name' => $record->name,
                    'slug' => $record->slug,
                    'city' => $record->city,
                    'district' => $record->district,
                    'area_type' => $record->area_type,
                ],
            ],
        ]);
    }

    public function nearby(NearbyNeighborhoodRequest $request)
    {
        if (DB::getDriverName() !== 'pgsql') {
            return response()->json([
                'success' => false,
                'message' => 'Nearby search requires PostgreSQL with PostGIS.',
            ], 503);
        }

        $filters = $request->validated();

        $latitude = (float) $filters['lat'];
        $longitude = (float) $filters['lng'];
        $radius = (int) ($filters['radius'] ?? 5000);
        $limit = (int) ($filters['limit'] ?? 20);

        $point = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';

        $neighborhoods = Neighborhood::query()
            ->select('neighborhoods.*')
            ->selectRaw(
                "ST_Distance(center, {$point}) AS distance_m",
                [$longitude, $latitude]
            )
            ->where('is_active', true)
            ->whereNotNull('center')
            ->whereRaw(
                "ST_DWithin(center, {$point}, ?)",
                [$longitude, $latitude, $radius]
            )
            ->orderBy('distance_m')
            ->limit($limit)
            ->get();

        return NeighborhoodResource::collection($neighborhoods)
            ->additional([
                'success' => true,
                'meta' => [
                    'radius_m' => $radius,
                    'limit' => $limit,
                    'count' => $neighborhoods->count(),
                    'coordinates' => [
                        'lat' => $latitude,
                        'lng' => $longitude,
                    ],
                ],
            ]);
    }
}