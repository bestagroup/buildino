<?php

namespace App\Http\Requests;

use App\Enums\ReservationApprovalType;
use App\Enums\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFacilityReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_facility_id' => 'sometimes|integer|exists:building_facilities,id',
            'facility_time_slot_id' => 'sometimes|nullable|integer|exists:facility_time_slots,id',
            'unit_id' => 'sometimes|integer|exists:units,id',
            'user_id' => 'sometimes|integer|exists:users,id',
            'reservation_date' => 'sometimes|date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'price' => 'sometimes|integer|min:0',
            'discount_amount' => 'sometimes|integer|min:0',
            'final_amount' => 'sometimes|integer|min:0',
            'status' => ['sometimes', 'sometimes', Rule::enum(ReservationStatus::class)],
            'approval_type' => ['sometimes', 'sometimes', Rule::enum(ReservationApprovalType::class)],
            'description' => 'sometimes|nullable|string',
        ];
    }
}
