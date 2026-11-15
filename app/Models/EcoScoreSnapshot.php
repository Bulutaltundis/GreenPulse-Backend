<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcoScoreSnapshot extends Model
{
    protected $fillable = [
        'neighborhood_id',
        'score',
        'observation_count',
        'average_impact',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'observation_count' => 'integer',
            'average_impact' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }
}