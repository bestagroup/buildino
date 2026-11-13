<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InvoicePeriodAllocation extends Model
{
    protected $fillable = ['unit_invoice_id', 'charge_period_id', 'sequence', 'amount', 'issued_invoice_id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'sequence' => 'integer'];
    }

    public function sourceInvoice(): BelongsTo { return $this->belongsTo(UnitInvoice::class, 'unit_invoice_id'); }
    public function chargePeriod(): BelongsTo { return $this->belongsTo(ChargePeriod::class); }
}
