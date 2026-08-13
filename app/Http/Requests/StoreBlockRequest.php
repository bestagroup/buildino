<?php

namespace App\Http\Requests;

use App\Models\Building;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $building = $this->route('building');

        $buildingId = $building instanceof Building
            ? $building->getKey()
            : (int) $building;

        return [
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('blocks', 'title')
                    ->where(
                        fn ($query) => $query->where(
                            'building_id',
                            $buildingId
                        )
                    ),
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
