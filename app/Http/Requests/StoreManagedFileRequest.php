<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File as FileRule;

class StoreManagedFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                FileRule::types(
                    config(
                        'file_management.allowed_extensions',
                        []
                    )
                )->max(
                    (int) config(
                        'file_management.max_upload_kilobytes',
                        51200
                    )
                ),
            ],
            'category' => [
                'sometimes',
                'string',
                Rule::in(
                    config('file_management.categories', [])
                ),
            ],
            'purpose' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'is_confidential' => [
                'sometimes',
                'boolean',
            ],
            'expires_at' => [
                'sometimes',
                'nullable',
                'date',
                'after:now',
            ],
        ];
    }
}
