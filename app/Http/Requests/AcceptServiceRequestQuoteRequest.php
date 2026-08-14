<?php

namespace App\Http\Requests;

use App\Enums\ServiceRequestPayerSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcceptServiceRequestQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payer_source' => [
                'required',
                Rule::enum(
                    ServiceRequestPayerSource::class
                ),
            ],
        ];
    }
}
