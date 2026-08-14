<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuestVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest' => [
                'required',
                'array',
            ],

            'guest.first_name' => [
                'required',
                'string',
                'max:255',
            ],

            'guest.last_name' => [
                'required',
                'string',
                'max:255',
            ],

            'guest.mobile' => [
                'nullable',
                'string',
                'max:20',
            ],

            'guest.national_code' => [
                'nullable',
                'string',
                'max:20',
            ],

            'guest.vehicle_plate' => [
                'nullable',
                'string',
                'max:255',
            ],

            'expected_entry_at' => [
                'nullable',
                'date',
            ],

            'expected_exit_at' => [
                'nullable',
                'date',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $entry = $this->input('expected_entry_at');
            $exit = $this->input('expected_exit_at');

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
