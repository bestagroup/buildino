<?php
namespace App\Http\Requests;

use App\Enums\FacilityWalletPayerSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayFacilityReservationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'payer_source' => [
                'required',
                Rule::enum(FacilityWalletPayerSource::class),
            ],
        ];
    }
}
