<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreObservationRequest;
use App\Http\Resources\ObservationResource;
use App\Models\Observation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Resources\ObservationAnalysisResource;

class ObservationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'neighborhood_id' => [
                'sometimes',
                'integer',
                'exists:neighborhoods,id',
            ],
            'category' => [
                'sometimes',
                'string',
                Rule::in(Observation::CATEGORIES),
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:50',
            ],
        ]);

        $query = Observation::query()
            ->approved()
            ->with([
                'user:id,name',
                'neighborhood:id,name,city,district',
            ]);

        if (isset($filters['neighborhood_id'])) {
            $query->where(
                'neighborhood_id',
                $filters['neighborhood_id']
            );
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        $observations = $query
            ->latest()
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return ObservationResource::collection($observations)
           ->additional(['success' => true]);
    }

    public function store(StoreObservationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $hasCoordinates = isset($data['latitude'], $data['longitude']);

        if (
            $hasCoordinates &&
            DB::connection()->getDriverName() !== 'pgsql'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Observation coordinates require PostgreSQL with PostGIS.',
            ], 503);
        }

        $observation = DB::transaction(function () use (
            $request,
            $data,
            $hasCoordinates
        ) {
            $observation = $request->user()
                ->observations()
                ->create([
                    'neighborhood_id' => $data['neighborhood_id'],
                    'category' => $data['category'],
                    'description' => $data['description'],
                    'status' => Observation::STATUS_PENDING,
                    'observed_at' => $data['observed_at'] ?? null,
                ]);

            if ($hasCoordinates) {
                DB::statement(
                    'UPDATE observations
                     SET location = ST_SetSRID(
                         ST_MakePoint(?, ?),
                         4326
                     )::geography
                     WHERE id = ?',
                    [
                        $data['longitude'],
                        $data['latitude'],
                        $observation->id,
                    ]
                );
            }

            return $observation;
        });

        $observation->load([
            'user:id,name',
            'neighborhood:id,name,city,district',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Observation created successfully.',
            'data' => new ObservationResource($observation),
        ], 201);
    }

    public function show(int $observation): JsonResponse
    {
        $user = auth('sanctum')->user();

        $query = Observation::query()
            ->whereKey($observation)
            ->where(function ($query) use ($user) {
                $query->where('status', Observation::STATUS_APPROVED);

                if ($user) {
                    $query->orWhere('user_id', $user->id);
                }
            });

        $record = $query->with([
            'user:id,name',
            'neighborhood:id,name,city,district',
        ])->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new ObservationResource($record),
        ]);
    }

    public function analyses(int $observation)
    {
        $user = auth('sanctum')->user();

        $record = Observation::query()
            ->whereKey($observation)
            ->where(function ($query) use ($user) {
                $query->where(
                    'status',
                    Observation::STATUS_APPROVED
                );

                if ($user) {
                    $query->orWhere('user_id', $user->id);

                    if ($user->isModerator()) {
                        $query->orWhereIn('status', [
                            Observation::STATUS_PENDING,
                            Observation::STATUS_REJECTED,
                        ]);
                    }
                }
            })
            ->firstOrFail();

        $analyses = $record->analyses()
            ->with('media:id,original_name')
            ->latest()
            ->get();

        return ObservationAnalysisResource::collection($analyses)
            ->additional([
                'success' => true,
            ]);
    }

    public function mine(Request $request)
    {
        $observations = $request->user()
            ->observations()
            ->with([
                'user:id,name',
                'neighborhood:id,name,city,district',
            ])
            ->latest()
            ->paginate(20);

        return ObservationResource::collection($observations)
            ->additional(['success' => true]);
    }

    public function destroy(Request $request, int $observation): JsonResponse
    {
        $record = $request->user()
            ->observations()
            ->findOrFail($observation);

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Observation deleted successfully.',
        ]);
    }
}
