<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\NeighborhoodController;
use App\Http\Controllers\Api\V1\DistrictController;
use App\Http\Controllers\Api\V1\ObservationController;
use App\Http\Controllers\Api\V1\ObservationMediaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ModerationController;
use App\Http\Controllers\Api\V1\NotificationController;

Route::prefix('v1')->group(function () {
    // Health
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'service' => 'GreenPulse API',
            'version' => '1.0.0',
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
        ]);
    });

    // Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:5,1');

        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    // Neighborhoods
    Route::get('/neighborhoods', [NeighborhoodController::class, 'index']);

    Route::get('/neighborhoods/nearby', [
        NeighborhoodController::class,
        'nearby',
    ]);

    Route::get(
        '/neighborhoods/{neighborhood}/eco-score',
        [NeighborhoodController::class, 'ecoScore']
    )->whereNumber('neighborhood');

    Route::get('/neighborhoods/{neighborhood}', [
        NeighborhoodController::class,
        'show',
    ])->whereNumber('neighborhood');

    Route::get(
        '/neighborhoods/{neighborhood}/geometry',
        [NeighborhoodController::class, 'geometry']
    )->whereNumber('neighborhood');

    // Districts
    Route::get('/districts', [DistrictController::class, 'index']);

    Route::get(
        '/districts/{district}/geometry',
        [DistrictController::class, 'geometry']
    );

    // Public observations
    Route::get('/observations', [ObservationController::class, 'index']);

    Route::get(
        '/observations/{observation}/analyses',
        [ObservationController::class, 'analyses']
    )->whereNumber('observation');

    Route::get('/observations/{observation}', [
        ObservationController::class,
        'show',
    ])->whereNumber('observation');

    // Authenticated observations and media
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/observations', [ObservationController::class, 'store'])
            ->middleware('throttle:10,1');

        Route::get('/me/observations', [ObservationController::class, 'mine']);

        // Notifications
        Route::get('/notifications', [
            NotificationController::class,
            'index',
        ]);

        Route::get('/notifications/unread-count', [
            NotificationController::class,
            'unreadCount',
        ]);

        Route::patch('/notifications/read-all', [
            NotificationController::class,
            'markAllRead',
        ]);

        Route::patch('/notifications/{notification}/read', [
            NotificationController::class,
            'markRead',
        ]);

        Route::delete('/observations/{observation}', [
            ObservationController::class,
            'destroy',
        ])->whereNumber('observation');

        // Upload media
        Route::post(
            '/observations/{observation}/media',
            [ObservationMediaController::class, 'store']
        )
            ->whereNumber('observation')
            ->middleware('throttle:20,1');

        // View media
        Route::get(
            '/observations/{observation}/media/{media}',
            [ObservationMediaController::class, 'show']
        )
            ->whereNumber('observation')
            ->whereNumber('media');

        // Delete media
        Route::delete(
            '/observations/{observation}/media/{media}',
            [ObservationMediaController::class, 'destroy']
        )
            ->whereNumber('observation')
            ->whereNumber('media');

        Route::middleware(['auth:sanctum', 'moderator'])
            ->prefix('moderation')
            ->group(function () {
                Route::get('/observations', [
                    ModerationController::class,
                    'index',
                ]);

                Route::get('/observations/{observation}', [
                    ModerationController::class,
                    'show',
                ])->whereNumber('observation');

                Route::post('/observations/{observation}/approve', [
                    ModerationController::class,
                    'approve',
                ])->whereNumber('observation');

                Route::post('/observations/{observation}/reject', [
                    ModerationController::class,
                    'reject',
                ])->whereNumber('observation');
            });
    });
});
