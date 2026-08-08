<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'documentable_type' => 'required|string|max:255',
            'documentable_id' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'document_number' => 'nullable|string|max:100',
            'document_date' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:document_date',
            'description' => 'nullable|string',
        ];
    }
}
