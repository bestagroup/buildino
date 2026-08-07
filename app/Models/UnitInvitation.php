<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'invited_by',
        'mobile',
        'email',
        'resident_type',
        'token',
        'status',
        'expires_at',
        'accepted_at',
        'accepted_user_id',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inviter(): BelongsTo
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

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || (
                $this->status === 'pending'
                && $this->expires_at?->isPast()
            );
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
