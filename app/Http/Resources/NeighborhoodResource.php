<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NeighborhoodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'city' => $this->city,
            'district' => $this->district,
            'population' => $this->population,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'distance_m' => $this->when(
                isset($this->distance_m),
                fn () => round((float) $this->distance_m, 2)
            ),
            'area_type' => $this->area_type,

            'buildings' => [
                'before_1998' => $this->buildings_before_1998,
                'under_1998_code' => $this->buildings_under_1998_code,
                'before_2007' => $this->buildings_before_2007,
                'under_2007_code' => $this->buildings_under_2007_code,
                'under_2018_code' => $this->buildings_under_2018_code,
                'total' => $this->total_buildings,
            ],
        ];
    }
}