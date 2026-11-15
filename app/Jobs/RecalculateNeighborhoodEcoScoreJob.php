<?php

namespace App\Jobs;

use App\Models\Neighborhood;
use App\Services\EcoScoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateNeighborhoodEcoScoreJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $neighborhoodId
    ) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(EcoScoreService $ecoScoreService): void
    {
        $neighborhood = Neighborhood::query()
            ->find($this->neighborhoodId);

        if (!$neighborhood) {
            return;
        }

        $ecoScoreService->calculateForNeighborhood($neighborhood);
    }
}
