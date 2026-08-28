<?php

namespace App\Models;

use App\Enums\ExpenseAllocationMethod;
use App\Enums\ExpensePayerResponsibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingExpenseAllocationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'building_id',
        'financial_category_id',
        'allocation_method',
        'payer_responsibility',
        'configuration',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allocation_method' => ExpenseAllocationMethod::class,
            'payer_responsibility' => ExpensePayerResponsibility::class,
            'configuration' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            FinancialCategory::class,
            'financial_category_id'
        );
    }
}
