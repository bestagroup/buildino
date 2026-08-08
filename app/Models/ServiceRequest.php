<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'service_requests';

    protected $fillable = [
        'request_number',
        'building_id',
        'unit_id',
        'requested_by',
        'type',
        'priority',
        'status',
        'title',
        'description',
        'assigned_to',
        'assigned_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
            'priority' => ServiceRequestPriority::class,
            'status' => ServiceRequestStatus::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function fileRelations(): MorphMany
    {
        return $this->morphMany(FileRelation::class, 'related');
    }
}
