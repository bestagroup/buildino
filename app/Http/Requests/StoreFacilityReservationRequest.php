<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFacilityReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facility_time_slot_id' => [
                'nullable',
                'integer',
                'exists:facility_time_slots,id',
            ],
            'unit_id' => [
                'required',
                'integer',
                'exists:units,id',
            ],
            'reservation_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'start_time' => [
                'nullable',
                'required_without:facility_time_slot_id',
                'date_format:H:i',
            ],
            'end_time' => [
                'nullable',
                'required_without:facility_time_slot_id',
                'date_format:H:i',
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
            if (
                $this->filled('facility_time_slot_id')
                || ! $this->filled('start_time')
                || ! $this->filled('end_time')
            ) {
                return;
            }

            if (
                strtotime((string) $this->input('end_time'))
                <= strtotime((string) $this->input('start_time'))
            ) {
                $validator->errors()->add(
                    'end_time',
                    'End time must be after start time.'
                );
            }
        });
    }
}
