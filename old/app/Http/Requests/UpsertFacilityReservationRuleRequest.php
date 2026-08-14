<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertFacilityReservationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'min_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'max_duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'min_advance_minutes' => ['sometimes', 'integer', 'min:0'],
            'max_advance_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'max_reservations_per_day' => ['nullable', 'integer', 'min:1'],
            'max_reservations_per_week' => ['nullable', 'integer', 'min:1'],
            'max_reservations_per_month' => ['nullable', 'integer', 'min:1'],
            'max_reservation_per_unit' => ['sometimes', 'integer', 'min:1'],
            'cancel_before_minutes' => ['sometimes', 'integer', 'min:0'],
            'cancellation_fee' => ['sometimes', 'integer', 'min:0'],
            'refund_percentage' => ['sometimes', 'integer', 'between:0,100'],
            'allow_guest' => ['sometimes', 'boolean'],
            'auto_confirm' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $min = $this->input('min_duration_minutes');
            $max = $this->input('max_duration_minutes');

            if ($min !== null && $max !== null && (int) $min > (int) $max) {
                $validator->errors()->add(
                    'max_duration_minutes',
                    'Maximum duration must be greater than or equal to minimum duration.'
                );
            }
        });
    }
}
