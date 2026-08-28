<?php

namespace App\Models;

use App\Enums\ExpenseAllocationMethod;
use App\Enums\ExpensePayerResponsibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuildingExpense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'building_id',
        'block_id',
        'fund_id',
        'financial_category_id',
        'allocation_method',
        'allocation_configuration',
        'payer_responsibility',
        'title',
        'amount',
        'expense_date',
        'invoice_number',
        'status',
        'description',
        'created_by',
        'approved_by',
        'approved_at',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'expense_date' => 'date',
            'allocation_method' => ExpenseAllocationMethod::class,
            'allocation_configuration' => 'array',
            'payer_responsibility' => ExpensePayerResponsibility::class,
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            FinancialCategory::class,
            'financial_category_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }
}
