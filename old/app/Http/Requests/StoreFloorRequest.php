<?php

namespace App\Http\Requests;

use App\Models\Block;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFloorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $block = $this->route('block');

        $blockId = $block instanceof Block
            ? $block->getKey()
            : (int) $block;

        return [
            'floor_number' => [
                'required',
                'integer',
                Rule::unique('floors', 'floor_number')
                    ->where(
                        fn ($query) => $query->where(
                            'block_id',
                            $blockId
                        )
                    ),
            ],

            'title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
        ];
    }
}
