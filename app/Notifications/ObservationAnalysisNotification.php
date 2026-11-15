<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ObservationAnalysisNotification extends Notification
{
    public function __construct(
        public int $observationId,
        public int $analysisId,
        public string $status,
        public ?string $summary = null,
        public ?string $detectedCategory = null,
        public ?float $confidence = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $completed = $this->status === 'completed';

        return [
            'type' => $completed
                ? 'observation_analysis_completed'
                : 'observation_analysis_failed',

            'title' => $completed
                ? 'Fotoğraf analizi tamamlandı'
                : 'Fotoğraf analizi başarısız oldu',

            'message' => $completed
                ? 'Çevre gözleminin fotoğraf analizi tamamlandı.'
                : 'Fotoğraf analizi tamamlanamadı. Daha sonra tekrar deneyebilirsin.',

            'observation_id' => $this->observationId,
            'analysis_id' => $this->analysisId,
            'status' => $this->status,
            'summary' => $this->summary,
            'detected_category' => $this->detectedCategory,
            'confidence' => $this->confidence,
        ];
    }
}
