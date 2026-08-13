<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceWalletSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_invoice_id',
        'wallet_transfer_id',
        'source_wallet_id',
        'destination_wallet_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(
            UnitInvoice::class,
            'unit_invoice_id'
        );
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(
            WalletTransfer::class,
            'wallet_transfer_id'
        );
    }
}
