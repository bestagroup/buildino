<?php

namespace App\Http\Requests;

use App\Models\Floor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFloorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $floor = $this->route('floor');

        if (! $floor instanceof Floor) {
            $floor = Floor::query()->find($floor);
        }

        return [
            'floor_number' => [
                'sometimes',
                'required',
                'integer',
                Rule::unique('floors', 'floor_number')
                    ->where(
                        fn ($query) => $query->where(
                            'block_id',
                            $floor?->block_id
                        )
                    )
                    ->ignore($floor?->getKey()),
            ],

            'title' => [
                'sometimes',
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
