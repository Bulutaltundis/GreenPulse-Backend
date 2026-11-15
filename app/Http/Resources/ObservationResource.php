<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'description' => $this->description,
            'status' => $this->status,
            'observed_at' => $this->observed_at,
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),

            'neighborhood' => $this->whenLoaded('neighborhood', fn () => [
                'id' => $this->neighborhood->id,
                'name' => $this->neighborhood->name,
                'district' => $this->neighborhood->district,
                'city' => $this->neighborhood->city,
            ]),
            'reviews' => $this->whenLoaded('reviews', fn () =>
                $this->reviews->map(fn ($review) => [
                    'id' => $review->id,
                    'previous_status' => $review->previous_status,
                    'new_status' => $review->new_status,
                    'reason' => $review->reason,
                    'created_at' => $review->created_at,
                    'moderator' => $review->moderator ? [
                        'id' => $review->moderator->id,
                        'name' => $review->moderator->name,
                    ] : null,
                    'impact_score' => $this->impact_score,
                ])
            ),
        ];
    }
}
