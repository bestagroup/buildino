<?php

namespace App\Models;

use App\Enums\WalletAccountingPostingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletAccountingPosting extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'wallet_transfer_id',
        'building_id',
        'financial_transaction_id',
        'status',
        'mapping_key',
        'reason',
        'mapping_snapshot',
        'attempts',
        'last_error',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WalletAccountingPostingStatus::class,
            'mapping_snapshot' => 'array',
            'attempts' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    public function walletTransfer(): BelongsTo
    {
        return $this->belongsTo(
            WalletTransfer::class
        );
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(
            FinancialTransaction::class
        );
    }
}
