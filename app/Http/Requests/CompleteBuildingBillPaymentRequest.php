<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteBuildingBillPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'provider_reference'=>['nullable','string','max:255'],
            'provider_payload'=>['nullable','array'],
        ];
    }
}
