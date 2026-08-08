<?php

namespace App\Models;

use App\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialReconciliation extends Model
{
    use HasFactory;

    protected $table = 'financial_reconciliations';

    protected $fillable = [
        'building_id',
        'financial_account_id',
        'reconciliation_date',
        'statement_balance',
        'ledger_balance',
        'difference',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'reconciliation_date' => 'date',
            'statement_balance' => 'integer',
            'ledger_balance' => 'integer',
            'difference' => 'integer',
            'approved_at' => 'datetime',
            'status' => ReconciliationStatus::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
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
