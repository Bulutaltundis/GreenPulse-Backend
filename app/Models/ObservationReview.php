<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObservationReview extends Model
{
    protected $fillable = [
        'observation_id',
        'moderator_id',
        'previous_status',
        'new_status',
        'reason',
        'impact_score',
    ];

    public function observation(): BelongsTo
    {
        return $this->belongsTo(Observation::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    protected function casts(): array
    {
        return [
            'impact_score' => 'integer',
        ];
    }
}
