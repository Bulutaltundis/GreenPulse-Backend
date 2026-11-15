<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Observation;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'neighborhood_id' => [
                'required',
                'integer',
                Rule::exists('neighborhoods', 'id')
                    ->where(fn (Builder $query) => $query->where('is_active', true)),
            ],

            'category' => [
                'required',
                'string',
                Rule::in(Observation::CATEGORIES),
            ],

            'description' => [
                'required',
                'string',
                'min:10',
                'max:5000',
            ],

            'observed_at' => [
                'nullable',
                'date',
                'before_or_equal:now',
            ],

            'latitude' => [
                'nullable',
                'required_with:longitude',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'required_with:latitude',
                'numeric',
                'between:-180,180',
            ],
        ];
    }
}
