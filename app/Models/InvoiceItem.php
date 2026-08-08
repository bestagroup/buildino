<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $table = 'invoice_items';

    protected $fillable = [
        'unit_invoice_id',
        'charge_item_id',
        'title',
        'description',
        'quantity',
        'unit_amount',
        'total_amount',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_amount' => 'integer',
            'total_amount' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function unitInvoice(): BelongsTo
    {
        return $this->belongsTo(UnitInvoice::class, 'unit_invoice_id');
    }

    public function chargeItem(): BelongsTo
    {
        return $this->belongsTo(ChargeItem::class, 'charge_item_id');
    }
}
