<?php

namespace App\Models;

use App\Enums\OccupancyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitOccupancy extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'unit_occupancies';

    protected $fillable = [
        'unit_id',
        'user_id',
        'occupancy_type',
        'starts_at',
        'ends_at',
        'is_primary',
        'is_active',
        'created_by',
        'ended_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'occupancy_type' => OccupancyType::class,
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }
}
