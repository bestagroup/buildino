<?php

namespace App\Http\Requests;

use App\Models\Block;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $block = $this->route('block');

        if (! $block instanceof Block) {
            $block = Block::query()->find($block);
        }

        return [
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('blocks', 'title')
                    ->where(
                        fn ($query) => $query->where(
                            'building_id',
                            $block?->building_id
                        )
                    )
                    ->ignore($block?->getKey()),
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
