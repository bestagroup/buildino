<?php

namespace App\Models;

use App\Enums\InvitationChannel;
use App\Enums\InvitationStatus;
use App\Enums\OccupancyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitInvitation extends Model
{
    use HasFactory;

    protected $table = 'unit_invitations';

    protected $fillable = [
        'unit_id',
        'invited_by',
        'mobile',
        'email',
        'relation_type',
        'channel',
        'token',
        'status',
        'sent_at',
        'expires_at',
        'accepted_at',
        'cancelled_at',
        'accepted_user_id',
    ];

    protected function casts(): array
    {
        return [
            'relation_type' => OccupancyType::class,
            'channel' => InvitationChannel::class,
            'status' => InvitationStatus::class,

            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class,
            'unit_id'
        );
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'invited_by'
        );
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'accepted_user_id'
        );
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return in_array(
            $this->status,
            [
                InvitationStatus::Pending,
                InvitationStatus::Sent,
            ],
            true
        ) && ! $this->isExpired();
    }
}
