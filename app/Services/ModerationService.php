<?php

namespace App\Services;

use App\Jobs\RecalculateNeighborhoodEcoScoreJob;
use App\Models\Observation;
use App\Models\User;
use App\Notifications\ObservationReviewedNotification;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ModerationService
{
    public function approve(
        int $observationId,
        User $moderator,
        int $impactScore,
        ?string $reason = null
    ): Observation {
        return $this->review(
            $observationId,
            $moderator,
            Observation::STATUS_APPROVED,
            $impactScore,
            $reason
        );
    }

    public function reject(
        int $observationId,
        User $moderator,
        string $reason
    ): Observation {
        return $this->review(
            $observationId,
            $moderator,
            Observation::STATUS_REJECTED,
            null,
            $reason
        );
    }

    private function review(
        int $observationId,
        User $moderator,
        string $newStatus,
        ?int $impactScore,
        ?string $reason
    ): Observation {
        return DB::transaction(function () use (
            $observationId,
            $moderator,
            $newStatus,
            $impactScore,
            $reason
        ) {
            $observation = Observation::query()
                ->lockForUpdate()
                ->findOrFail($observationId);

            if ($observation->status !== Observation::STATUS_PENDING) {
                throw new ConflictHttpException(
                    'Only pending observations can be reviewed.'
                );
            }

            $previousStatus = $observation->status;

            $observation->status = $newStatus;
            $observation->reviewed_at = now();
            $observation->impact_score = $impactScore;
            $observation->save();

            $observation->reviews()->create([
                'moderator_id' => $moderator->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'impact_score' => $impactScore,
                'reason' => $reason,
            ]);

            $observation = $observation->fresh([
                'user',
                'neighborhood',
                'reviews.moderator',
            ]);

            // Kullanıcıya veritabanı bildirimi oluştur.
            $observation->user->notify(
                new ObservationReviewedNotification(
                    observationId: (int) $observation->id,
                    neighborhoodId: (int) $observation->neighborhood_id,
                    neighborhoodName: $observation->neighborhood->name,
                    status: $newStatus,
                    reason: $reason,
                )
            );

            // Yalnızca onaylanan gözlemler Eco Score'u günceller.
            if ($newStatus === Observation::STATUS_APPROVED) {
                RecalculateNeighborhoodEcoScoreJob::dispatch(
                    (int) $observation->neighborhood_id
                )->afterCommit();
            }

            return $observation;
        });
    }
}
