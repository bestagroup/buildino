<?php

namespace App\Models;

use App\Enums\GuestVisitStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestVisit extends Model
{
    use HasFactory;

    protected $table = 'guest_visits';

    protected $fillable = [
        'guest_id',
        'unit_id',
        'registered_by',
        'expected_entry_at',
        'expected_exit_at',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'expected_entry_at' => 'datetime',
            'expected_exit_at' => 'datetime',
            'status' => GuestVisitStatus::class,
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(
            Guest::class,
            'guest_id'
        );
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class,
            'unit_id'
        );
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'registered_by'
        );
    }

    public function guestAccessLogs(): HasMany
    {
        return $this->hasMany(
            GuestAccessLog::class,
            'guest_visit_id'
        );
    }
}
