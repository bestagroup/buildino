<?php

namespace App\Models;

use App\Enums\FinancialTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialTransaction extends Model
{
    use HasFactory;

    protected $table = 'financial_transactions';

    protected $fillable = [
        'uuid',
        'building_id',
        'transaction_type',
        'occurred_at',
        'reference_type',
        'reference_id',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'reference_id' => 'integer',
            'transaction_type' => FinancialTransactionType::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function financialLedgerEntries(): HasMany
    {
        return $this->hasMany(FinancialLedgerEntry::class, 'financial_transaction_id');
    }
}
