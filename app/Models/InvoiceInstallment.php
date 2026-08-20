<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceInstallment extends Model
{
    use HasFactory;

    protected $table = 'invoice_installments';

    protected $fillable = [
        'unit_invoice_id',
        'installment_number',
        'due_date',
        'amount',
        'paid_amount',
        'status',
        'paid_at',
        'penalty_amount',
        'waived_amount',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'installment_number' => 'integer',
            'due_date' => 'date',
            'amount' => 'integer',
            'paid_amount' => 'integer',
            'paid_at' => 'datetime',
            'penalty_amount' => 'integer',
            'waived_amount' => 'integer',
            'metadata' => 'array',
            'status' => InstallmentStatus::class,
        ];
    }

    public function unitInvoice(): BelongsTo
    {
        return $this->belongsTo(UnitInvoice::class, 'unit_invoice_id');
    }
}
