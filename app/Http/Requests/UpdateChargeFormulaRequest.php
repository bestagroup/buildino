<?php

namespace App\Http\Requests;

use App\Enums\ChargeCalculationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChargeFormulaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'=>['sometimes','required','string','max:255'],
            'calculation_type'=>['sometimes',Rule::enum(ChargeCalculationType::class)],
            'configuration'=>['sometimes','nullable','array'],
            'is_active'=>['sometimes','boolean'],
            'items'=>['sometimes','array','min:1'],
            'items.*.financial_category_id'=>['nullable','integer','exists:financial_categories,id'],
            'items.*.title'=>['required_with:items','string','max:255'],
            'items.*.base_amount'=>['required_with:items','integer','min:0'],
            'items.*.configuration'=>['nullable','array'],
            'builder'=>[
                'sometimes','array',
                'required_array_keys:calculation_type,items',
            ],
            'builder.calculation_type'=>[
                'required_with:builder',
                Rule::in(['fixed','area','persons']),
            ],
            'builder.items'=>['required_with:builder','array','min:1','max:20'],
            'builder.items.*.financial_category_id'=>[
                'nullable','integer','exists:financial_categories,id',
            ],
            'builder.items.*.title'=>[
                'required_with:builder.items','string','max:255',
            ],
            'builder.items.*.base_amount'=>[
                'required_with:builder.items','integer','min:0',
            ],
        ];
    }
}
