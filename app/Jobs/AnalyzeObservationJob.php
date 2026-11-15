<?php

namespace App\Jobs;

use App\Models\ObservationAnalysis;
use App\Services\GeminiAnalysisService;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;
use App\Notifications\ObservationAnalysisNotification;
use Illuminate\Support\Facades\DB;

class AnalyzeObservationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $maxExceptions = 3;

    public function __construct(
        public ObservationAnalysis $analysis
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(
                "observation-analysis:{$this->analysis->getKey()}"
            ))
                ->releaseAfter(30)
                ->expireAfter(180),
        ];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(GeminiAnalysisService $gemini): void
    {
        $analysis = $this->analysis->fresh();

        if (
            !$analysis ||
            $analysis->status === ObservationAnalysis::STATUS_COMPLETED
        ) {
            return;
        }

        $analysis->update([
            'status' => ObservationAnalysis::STATUS_PROCESSING,
            'started_at' => now(),
            'error_code' => null,
            'error_message' => null,
        ]);

        $result = $gemini->analyze($analysis);

        $analysis->update([
            'status' => ObservationAnalysis::STATUS_COMPLETED,
            'summary' => $result['summary'],
            'detected_category' => $result['detected_category'],
            'environmental_issue' => $result['environmental_issue'],
            'confidence' => round($result['confidence'] * 100, 2),
            'findings' => $result['findings'],
            'recommendations' => $result['recommendations'],
            'completed_at' => now(),
        ]);

        $analysis->loadMissing('observation.user');

        $analysis->observation->user->notify(
            new ObservationAnalysisNotification(
                observationId: (int) $analysis->observation_id,
                analysisId: (int) $analysis->id,
                status: ObservationAnalysis::STATUS_COMPLETED,
                summary: $analysis->summary,
                detectedCategory: $analysis->detected_category,
                confidence: (float) $analysis->confidence,
            )
        );
    }

    public function failed(?Throwable $exception): void
    {
        $analysis = $this->analysis->fresh();

        if (
            !$analysis ||
            $analysis->status === ObservationAnalysis::STATUS_COMPLETED
        ) {
            return;
        }

        $analysis->update([
            'status' => ObservationAnalysis::STATUS_FAILED,
            'error_code' => 'ANALYSIS_FAILED',
            'error_message' => 'Fotoğraf analizi tamamlanamadı.',
            'completed_at' => now(),
        ]);

        $analysis->loadMissing('observation.user');

        $analysis->observation->user->notify(
            new ObservationAnalysisNotification(
                observationId: (int) $analysis->observation_id,
                analysisId: (int) $analysis->id,
                status: ObservationAnalysis::STATUS_FAILED,
            )
        );
    }
}
