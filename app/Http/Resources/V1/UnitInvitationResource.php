<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'invited_by' => $this->invited_by,
            'invited_user_id' => $this->invited_user_id,

            'mobile' => $this->mobile,
            'email' => $this->email,
            'message' => $this->message,

            'relation_type' => is_object($this->relation_type)
                ? $this->relation_type->value
                : $this->relation_type,

            'planned_occupancy' => $this->occupancy_data === null ? null : [
                'occupancy_type' => $this->occupancy_data['occupancy_type'],
                'starts_at' => $this->occupancy_data['starts_at'],
                'ends_at' => $this->occupancy_data['ends_at'] ?? null,
                'is_primary' => (bool) ($this->occupancy_data['is_primary'] ?? false),
            ],

            'channel' => is_object($this->channel)
                ? $this->channel->value
                : $this->channel,

            /*
             * The stored token hash is intentionally never exposed.
             */
            'status' => is_object($this->status)
                ? $this->status->value
                : $this->status,

            'sent_at' => $this->sent_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),

            'accepted_user_id' => $this->accepted_user_id,

            'unit' => $this->whenLoaded(
                'unit',
                fn (): array => [
                    'id' => $this->unit->id,
                    'floor_id' => $this->unit->floor_id,
                    'unit_number' => $this->unit->unit_number,
                    'title' => $this->unit->title,
                ]
            ),

            'inviter' => $this->whenLoaded(
                'invitedBy',
                fn (): array => [
                    'id' => $this->invitedBy->id,
                    'first_name' => $this->invitedBy->first_name,
                    'last_name' => $this->invitedBy->last_name,
                ]
            ),

            'invited_user' => $this->whenLoaded(
                'invitedUser',
                fn (): ?array => $this->invitedUser
                    ? [
                        'id' => $this->invitedUser->id,
                        'display_name' => trim(
                            "{$this->invitedUser->first_name} {$this->invitedUser->last_name}"
                        ),
                        'mobile' => $this->invitedUser->mobile,
                        'email' => $this->invitedUser->email,
                        'account_active' => (bool) $this->invitedUser->is_active && ! (bool) $this->invitedUser->is_blocked,
                        'identity_verified' => $this->invitedUser->mobile_verified_at !== null || $this->invitedUser->email_verified_at !== null,
                    ]
                    : null
            ),

            'accepted_user' => $this->whenLoaded(
                'acceptedUser',
                fn (): ?array => $this->acceptedUser
                    ? [
                        'id' => $this->acceptedUser->id,
                        'first_name' => $this->acceptedUser->first_name,
                        'last_name' => $this->acceptedUser->last_name,
                        'mobile' => $this->acceptedUser->mobile,
                        'email' => $this->acceptedUser->email,
                    ]
                    : null
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
