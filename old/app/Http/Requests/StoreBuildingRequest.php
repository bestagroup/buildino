<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'complex_id' => [
                'required',
                'integer',
                'exists:complexes,id',
            ],

            'code' => [
                'required',
                'string',
                'max:100',
                'unique:buildings,code',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'building_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            'postal_code' => [
                'nullable',
                'string',
                'max:20',
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'timezone' => [
                'nullable',
                'timezone',
            ],

            'currency' => [
                'nullable',
                'string',
                'size:3',
            ],

            'floors_count' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'units_count' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'parking_count' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'storage_count' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'construction_year' => [
                'nullable',
                'integer',
                'min:1200',
                'max:2200',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
