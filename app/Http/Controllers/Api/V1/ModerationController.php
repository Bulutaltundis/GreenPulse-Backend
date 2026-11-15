<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ObservationResource;
use App\Models\Observation;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ModerationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $observations = Observation::query()
            ->pending()
            ->with([
                'user:id,name',
                'neighborhood:id,name,city,district',
            ])
            ->latest()
            ->paginate(20);

        return ObservationResource::collection($observations)
            ->additional([
                'success' => true,
            ]);
    }

    public function show(int $observation): JsonResponse
    {
        $record = Observation::query()
            ->pending()
            ->with([
                'user:id,name',
                'neighborhood:id,name,city,district',
                'reviews.moderator:id,name',
            ])
            ->findOrFail($observation);

        return response()->json([
            'success' => true,
            'data' => new ObservationResource($record),
        ]);
    }

    public function approve(
        Request $request,
        int $observation,
        ModerationService $service
    ): JsonResponse {
        $data = $request->validate([
            'impact_score' => ['required', 'integer', 'between:-5,5'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = $service->approve(
            $observation,
            $request->user(),
            $data['impact_score'],
            $data['reason'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Observation approved successfully.',
            'data' => new ObservationResource($record),
        ]);
    }

    public function reject(
        Request $request,
        int $observation,
        ModerationService $service
    ): JsonResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $record = $service->reject(
            $observation,
            $request->user(),
            $data['reason']
        );

        return response()->json([
            'success' => true,
            'message' => 'Observation rejected successfully.',
            'data' => new ObservationResource($record),
        ]);
    }
}
