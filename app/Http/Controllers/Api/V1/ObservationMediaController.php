<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreObservationMediaRequest;
use App\Jobs\AnalyzeObservationJob;
use App\Models\ObservationAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ObservationMediaController extends Controller
{
    public function store(
        StoreObservationMediaRequest $request,
        int $observation
    ): JsonResponse {
        $record = $request->user()
            ->observations()
            ->findOrFail($observation);

        $files = $request->file('files');
        $storedPaths = [];

        try {
            $media = DB::transaction(function () use (
                $record,
                $files,
                &$storedPaths
            ) {
                $createdMedia = [];

                foreach ($files as $file) {
                    $path = $file->store(
                        "observations/{$record->id}",
                        'private'
                    );

                    if ($path === false) {
                        throw new \RuntimeException(
                            'Unable to store uploaded image.'
                        );
                    }

                    $storedPaths[] = $path;

                    $dimensions = getimagesize($file->getRealPath());

                    $item = $record->media()->create([
                        'disk' => 'private',
                        'path' => $path,
                        'original_name' => Str::limit(
                            basename($file->getClientOriginalName()),
                            255,
                            ''
                        ),
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                        'width' => $dimensions[0] ?? null,
                        'height' => $dimensions[1] ?? null,
                    ]);

                    $createdMedia[] = $item;

                    $analysis = ObservationAnalysis::create([
                        'observation_id' => $record->id,
                        'observation_media_id' => $item->id,
                        'status' => ObservationAnalysis::STATUS_QUEUED,
                        'provider' => 'gemini',
                        'model' => config('services.gemini.model'),
                    ]);

                    AnalyzeObservationJob::dispatch($analysis)
                        ->afterCommit();
                }

                return $createdMedia;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('private')->delete($path);
            }

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded successfully.',
            'data' => collect($media)->map(fn ($item) => [
                'id' => $item->id,
                'original_name' => $item->original_name,
                'mime_type' => $item->mime_type,
                'size_bytes' => $item->size_bytes,
                'width' => $item->width,
                'height' => $item->height,
                'created_at' => $item->created_at,
            ]),
        ], 201);
    }

    public function destroy(
        Request $request,
        int $observation,
        int $media
    ): JsonResponse {
        $record = $request->user()
            ->observations()
            ->findOrFail($observation);

        $item = $record->media()->findOrFail($media);

        Storage::disk($item->disk)->delete($item->path);

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully.',
        ]);
    }

    public function show(
        Request $request,
        int $observation,
        int $media
    ) {
        $record = $request->user()
            ->observations()
            ->findOrFail($observation);

        $item = $record->media()->findOrFail($media);

        abort_unless(
            Storage::disk($item->disk)->exists($item->path),
            404
        );

        return Storage::disk($item->disk)->response(
            $item->path,
            $item->original_name,
            [
                'Content-Type' => $item->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }
}
