<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChargeItem extends Model
{
    use HasFactory;

    protected $table = 'charge_items';

    protected $fillable = [
        'charge_formula_id',
        'financial_category_id',
        'title',
        'base_amount',
        'configuration',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'integer',
            'configuration' => 'array',
        ];
    }

    public function chargeFormula(): BelongsTo
    {
        return $this->belongsTo(ChargeFormula::class, 'charge_formula_id');
    }

    public function financialCategory(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'financial_category_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'charge_item_id');
    }
}
