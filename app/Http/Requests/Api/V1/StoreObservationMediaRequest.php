<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreObservationMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'files' => [
                'required',
                'array',
                'min:1',
                'max:5',
            ],

            'files.*' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'At least one image is required.',
            'files.max' => 'You can upload up to 5 images at once.',
            'files.*.image' => 'Every uploaded file must be a valid image.',
            'files.*.mimes' => 'Only JPEG, PNG and WebP images are supported.',
            'files.*.max' => 'Each image must be smaller than 10 MB.',
        ];
    }
}
