<?php

namespace App\Services;

use App\Models\EcoScoreSnapshot;
use App\Models\Neighborhood;
use App\Models\Observation;
use Illuminate\Support\Facades\DB;

class EcoScoreService
{
    public function calculateForNeighborhood(
        Neighborhood $neighborhood
    ): ?EcoScoreSnapshot {
        return DB::transaction(function () use ($neighborhood) {
            $result = Observation::query()
                ->where('neighborhood_id', $neighborhood->id)
                ->where('status', Observation::STATUS_APPROVED)
                ->whereNotNull('impact_score')
                ->selectRaw('
                    COUNT(*) as observation_count,
                    AVG(impact_score) as average_impact
                ')
                ->first();

            $count = (int) $result->observation_count;

            // Puan hesaplamak için yeterli veri yok.
            if ($count === 0) {
                return null;
            }

            $averageImpact = (float) $result->average_impact;

            // -5 etki = 0 puan, 0 etki = 50 puan, +5 etki = 100 puan.
            $score = (int) round(
                max(0, min(100, 50 + ($averageImpact * 10)))
            );

            $latest = EcoScoreSnapshot::query()
                ->where('neighborhood_id', $neighborhood->id)
                ->latest('calculated_at')
                ->first();

            $roundedAverage = round($averageImpact, 2);

            if (
                $latest &&
                $latest->score === $score &&
                $latest->observation_count === $count &&
                abs((float) $latest->average_impact - $roundedAverage) < 0.005
            ) {
                return $latest;
            }

            return EcoScoreSnapshot::create([
                'neighborhood_id' => $neighborhood->id,
                'score' => $score,
                'observation_count' => $count,
                'average_impact' => round($averageImpact, 2),
                'calculated_at' => now(),
            ]);
        });
    }
}