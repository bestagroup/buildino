<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Enums\InvitationChannel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'channel' => InvitationChannel::class,
            'status' => InvitationStatus::class,
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }
}
