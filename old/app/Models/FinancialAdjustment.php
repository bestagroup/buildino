<?php

namespace App\Models;

use App\Enums\FinancialAdjustmentType;
use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAdjustment extends Model
{
    use HasFactory;

    protected $table = 'financial_adjustments';

    protected $fillable = [
        'unit_invoice_id',
        'type',
        'amount',
        'reason',
        'status',
        'effective_at',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'effective_at' => 'datetime',
            'approved_at' => 'datetime',
            'type' => FinancialAdjustmentType::class,
            'status' => ApprovalStatus::class,
        ];
    }

    public function unitInvoice(): BelongsTo
    {
        return $this->belongsTo(UnitInvoice::class, 'unit_invoice_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
