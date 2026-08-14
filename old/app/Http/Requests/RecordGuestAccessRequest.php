<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordGuestAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occurred_at' => [
                'nullable',
                'date',
                'before_or_equal:now',
            ],

            'gate' => [
                'nullable',
                'string',
                'max:255',
            ],

            'entry_method' => [
                'nullable',
                'string',
                'max:30',
            ],

            'vehicle_plate' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
