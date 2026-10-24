<?php

namespace App\Http\Requests;

use App\Enums\ChargeCalculationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChargeFormulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'calculation_type' => [
                'sometimes',
                Rule::enum(
                    ChargeCalculationType::class
                ),
            ],
            'configuration' => [
                'sometimes',
                'nullable',
                'array',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'items' => [
                'sometimes',
                'array',
                'size:1',
            ],
            'items.*.financial_category_id' => [
                'required_with:items',
                'integer',
                'exists:financial_categories,id',
            ],
            'items.*.title' => [
                'required_with:items',
                'string',
                'max:255',
            ],
            'items.*.base_amount' => [
                'required_with:items',
                'integer',
                'min:0',
            ],
            'items.*.configuration' => [
                'nullable',
                'array',
            ],

            'builder' => [
                'sometimes',
                'array',
                'required_array_keys:calculation_type,financial_category_id',
            ],
            'builder.calculation_type' => [
                'required_with:builder',
                Rule::enum(
                    ChargeCalculationType::class
                ),
            ],
            'builder.financial_category_id' => [
                'required_with:builder',
                'integer',
                'exists:financial_categories,id',
            ],
            'builder.fixed_amount' => [
                'nullable',
                'integer',
                'min:0',
                Rule::requiredIf(
                    fn (): bool =>
                        $this->input(
                            'builder.calculation_type'
                        ) === ChargeCalculationType::Fixed->value
                ),
            ],
            'builder.floor_coefficients' => [
                'nullable',
                'array',
                Rule::requiredIf(
                    fn (): bool =>
                        $this->input(
                            'builder.calculation_type'
                        ) === ChargeCalculationType::FloorCoefficient->value
                ),
            ],
            'builder.floor_coefficients.*' => [
                'numeric',
                'gt:0',
            ],
        ];
    }
}
