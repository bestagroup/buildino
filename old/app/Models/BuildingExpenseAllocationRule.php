<?php

namespace App\Models;

use App\Enums\ExpenseAllocationMethod;
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
        'configuration',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allocation_method' => ExpenseAllocationMethod::class,
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
