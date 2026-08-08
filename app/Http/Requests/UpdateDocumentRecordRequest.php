<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'documentable_type' => 'sometimes|string|max:255',
            'documentable_id' => 'sometimes|integer|min:1',
            'title' => 'sometimes|string|max:255',
            'document_type' => ['sometimes', 'required', Rule::enum(DocumentType::class)],
            'document_number' => 'sometimes|nullable|string|max:100',
            'document_date' => 'sometimes|nullable|date',
            'expires_at' => 'sometimes|nullable|date|after_or_equal:document_date',
            'description' => 'sometimes|nullable|string',
        ];
    }
}
