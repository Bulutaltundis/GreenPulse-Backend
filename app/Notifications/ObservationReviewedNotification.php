<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ObservationReviewedNotification extends Notification
{
    public function __construct(
        public int $observationId,
        public int $neighborhoodId,
        public string $neighborhoodName,
        public string $status,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $approved = $this->status === 'approved';

        return [
            'type' => $approved
                ? 'observation_approved'
                : 'observation_rejected',

            'title' => $approved
                ? 'Gözlemin onaylandı'
                : 'Gözlemin reddedildi',

            'message' => $approved
                ? "{$this->neighborhoodName} mahallesindeki çevre gözlemin onaylandı."
                : "{$this->neighborhoodName} mahallesindeki çevre gözlemin reddedildi.",

            'observation_id' => $this->observationId,
            'neighborhood_id' => $this->neighborhoodId,
            'neighborhood_name' => $this->neighborhoodName,
            'status' => $this->status,
            'reason' => $this->reason,
        ];
    }
}
