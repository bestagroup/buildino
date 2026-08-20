<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'unit_invoices';

    protected $fillable = [
        'building_id','unit_id','charge_period_id','invoice_number',
        'issue_date','due_date','period_start','period_end',
        'subtotal','discount_amount','penalty_amount','waived_penalty_amount','total_amount',
        'paid_amount','outstanding_amount','status','description','created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'=>'date','due_date'=>'date','period_start'=>'date',
            'period_end'=>'date','subtotal'=>'integer','discount_amount'=>'integer',
            'penalty_amount'=>'integer','waived_penalty_amount'=>'integer','total_amount'=>'integer',
            'paid_amount'=>'integer','outstanding_amount'=>'integer',
            'status'=>InvoiceStatus::class,
        ];
    }

    public function building(): BelongsTo { return $this->belongsTo(Building::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
    public function chargePeriod(): BelongsTo { return $this->belongsTo(ChargePeriod::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function invoiceItems(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function invoiceInstallments(): HasMany { return $this->hasMany(InvoiceInstallment::class); }
    public function financialAdjustments(): HasMany { return $this->hasMany(FinancialAdjustment::class); }
    public function paymentAllocations(): MorphMany { return $this->morphMany(PaymentAllocation::class, 'payable'); }
    public function fileRelations(): MorphMany { return $this->morphMany(FileRelation::class, 'related'); }
}
