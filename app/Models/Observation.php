<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Observation extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const CATEGORIES = [
        'air_quality',
        'water_quality',
        'waste',
        'noise',
        'green_space',
        'biodiversity',
        'infrastructure',
        'other',
    ];

    protected $fillable = [
        'user_id',
        'neighborhood_id',
        'category',
        'description',
        'status',
        'observed_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'impact_score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ObservationMedia::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ObservationReview::class)
            ->latest();
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(ObservationAnalysis::class);
    }
}
