<?php

namespace App\Http\Requests;

use App\Enums\ChargeCalculationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChargeFormulaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'=>['required','string','max:255'],
            'calculation_type'=>['required',Rule::enum(ChargeCalculationType::class)],
            'configuration'=>['nullable','array'],
            'is_active'=>['sometimes','boolean'],
            'items'=>['required','array','min:1'],
            'items.*.financial_category_id'=>['nullable','integer','exists:financial_categories,id'],
            'items.*.title'=>['required','string','max:255'],
            'items.*.base_amount'=>['required','integer','min:0'],
            'items.*.configuration'=>['nullable','array'],
        ];
    }
}
