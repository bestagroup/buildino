<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitGuest extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'registered_by',
        'first_name',
        'last_name',
        'mobile',
        'national_code',
        'vehicle_number',
        'expected_entry_at',
        'expected_exit_at',
        'entry_at',
        'exit_at',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'expected_entry_at' => 'datetime',
            'expected_exit_at' => 'datetime',
            'entry_at' => 'datetime',
            'exit_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'registered_by'
        );
    }

    public function scopeInvited($query)
    {
        return $query->where('status', 'invited');
    }

    public function scopeEntered($query)
    {
        return $query->where('status', 'entered');
    }

    public function scopeExited($query)
    {
        return $query->where('status', 'exited');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function isInvited(): bool
    {
        return $this->status === 'invited';
    }

    public function isEntered(): bool
    {
        return $this->status === 'entered';
    }

    public function isExited(): bool
    {
        return $this->status === 'exited';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getFullNameAttribute(): string
    {
        return trim(
            $this->first_name . ' ' . $this->last_name
        );
    }
}
