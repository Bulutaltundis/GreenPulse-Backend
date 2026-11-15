<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Neighborhood extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'city',
        'district',
        'population',
        'is_active',
        'area_type',
        'source',
        'source_id',
        'source_object_id',
        'source_guid',
        'source_district_guid',
        'buildings_before_1998',
        'buildings_under_1998_code',
        'buildings_before_2007',
        'buildings_under_2007_code',
        'buildings_under_2018_code',
        'total_buildings',
    ];

    protected function casts(): array
    {
        return [
            'population' => 'integer',
            'is_active' => 'boolean',
            'source_id' => 'integer',
            'source_object_id' => 'integer',
            'buildings_before_1998' => 'integer',
            'buildings_under_1998_code' => 'integer',
            'buildings_before_2007' => 'integer',
            'buildings_under_2007_code' => 'integer',
            'buildings_under_2018_code' => 'integer',
            'total_buildings' => 'integer',
        ];
    }

    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    public function ecoScoreSnapshots(): HasMany
    {
        return $this->hasMany(EcoScoreSnapshot::class);
    }
}
