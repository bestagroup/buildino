<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuestVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_entry_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'expected_exit_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $visit = $this->route(
                'guestVisit'
            );

            $entry = $this->has('expected_entry_at')
                ? $this->input('expected_entry_at')
                : $visit?->expected_entry_at;

            $exit = $this->has('expected_exit_at')
                ? $this->input('expected_exit_at')
                : $visit?->expected_exit_at;

            if (
                $entry
                && $exit
                && strtotime((string) $exit)
                    < strtotime((string) $entry)
            ) {
                $validator->errors()->add(
                    'expected_exit_at',
                    'The expected exit time cannot be before the expected entry time.'
                );
            }
        });
    }
}
