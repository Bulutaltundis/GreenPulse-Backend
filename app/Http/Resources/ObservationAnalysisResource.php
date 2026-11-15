<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObservationAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'media_id' => $this->observation_media_id,
            'status' => $this->status,

            'summary' => $this->summary,
            'detected_category' => $this->detected_category,
            'environmental_issue' => $this->environmental_issue,
            'confidence' => $this->confidence,

            'findings' => $this->findings,
            'recommendations' => $this->recommendations,

            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
        ];
    }
}