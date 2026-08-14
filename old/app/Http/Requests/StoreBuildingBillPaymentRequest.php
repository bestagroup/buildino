<?php
namespace App\Http\Requests;

use App\Enums\BuildingBillType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBuildingBillPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'bill_type'=>['required',Rule::enum(BuildingBillType::class)],
            'bill_identifier'=>['nullable','string','max:100'],
            'payment_identifier'=>['nullable','string','max:100'],
            'amount'=>['required','integer','min:1'],
            'provider'=>['nullable','string','max:100'],
        ];
    }
}
