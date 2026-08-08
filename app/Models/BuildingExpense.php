<?php

namespace App\Models;

use App\Enums\FinancialOperationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuildingExpense extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'building_expenses';

    protected $fillable = [
        'building_id',
        'fund_id',
        'financial_category_id',
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
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'status' => FinancialOperationStatus::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class, 'fund_id');
    }

    public function financialCategory(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'financial_category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function fileRelations(): MorphMany
    {
        return $this->morphMany(FileRelation::class, 'related');
    }
}
