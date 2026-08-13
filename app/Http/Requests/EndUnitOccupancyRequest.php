<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EndUnitOccupancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ends_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
