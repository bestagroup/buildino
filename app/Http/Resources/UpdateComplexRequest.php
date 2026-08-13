<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $complex = $this->route('complex');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:100',

                Rule::unique('complexes', 'code')
                    ->ignore(
                        $complex?->getKey() ?? $complex
                    ),
            ],

            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'province' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'city' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'address' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'postal_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'latitude' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
