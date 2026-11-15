<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObservationAnalysis extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'observation_id',
        'observation_media_id',
        'status',
        'provider',
        'model',
        'summary',
        'detected_category',
        'environmental_issue',
        'confidence',
        'findings',
        'recommendations',
        'error_code',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'environmental_issue' => 'boolean',
            'confidence' => 'decimal:2',
            'findings' => 'array',
            'recommendations' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function observation(): BelongsTo
    {
        return $this->belongsTo(Observation::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(
            ObservationMedia::class,
            'observation_media_id'
        );
    }
}