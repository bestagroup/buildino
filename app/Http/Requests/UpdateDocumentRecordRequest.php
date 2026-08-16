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
            'documentable_type' => ['prohibited'],
            'documentable_id' => ['prohibited'],
            'title' => 'sometimes|string|max:255',
            'document_type' => ['sometimes', Rule::enum(DocumentType::class)],
            'document_number' => 'sometimes|nullable|string|max:100',
            'document_date' => 'sometimes|nullable|date',
            'expires_at' => 'sometimes|nullable|date|after_or_equal:document_date',
            'description' => 'sometimes|nullable|string',
        ];
    }
}
