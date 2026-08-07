<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_resident_id',
        'unit_id',
        'user_id',
        'resident_type',
        'start_date',
        'end_date',
        'change_reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function unitResident(): BelongsTo
    {
        return $this->belongsTo(UnitResident::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function scopeCurrent($query)
    {
        return $query->whereNull('end_date');
    }

    public function scopeHistorical($query)
    {
        return $query->whereNotNull('end_date');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('resident_type', $type);
    }

    public function scopeByReason($query, string $reason)
    {
        return $query->where('change_reason', $reason);
    }
}
