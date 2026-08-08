<?php

namespace App\Models;

use App\Enums\SupportTicketStatus;
use App\Enums\SupportPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'support_tickets';

    protected $fillable = [
        'user_id',
        'building_id',
        'unit_id',
        'support_category_id',
        'ticket_number',
        'subject',
        'description',
        'priority',
        'status',
        'assigned_to',
        'assigned_at',
        'first_response_at',
        'response_due_at',
        'resolution_due_at',
        'resolved_at',
        'closed_at',
        'resolution',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'first_response_at' => 'datetime',
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'priority' => SupportPriority::class,
            'status' => SupportTicketStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function supportCategory(): BelongsTo
    {
        return $this->belongsTo(SupportCategory::class, 'support_category_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function supportMessages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'support_ticket_id');
    }

    public function fileRelations(): MorphMany
    {
        return $this->morphMany(FileRelation::class, 'related');
    }
}
