<?php

namespace App\Models;

use App\Enums\FinancialCategoryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialCategory extends Model
{
    use HasFactory;

    protected $table = 'financial_categories';

    protected $fillable = [
        'building_id',
        'parent_id',
        'default_key',
        'title_key',
        'title',
        'type',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            if ($category->parent_id !== null) {
                $parent = self::find($category->parent_id);
                if (! $parent || $parent->parent_id !== null || $parent->id === $category->id
                    || $parent->building_id !== $category->building_id || $parent->type !== $category->type
                    || ($category->exists && $category->children()->exists())) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['parent_id' => 'ساختار دسته باید دو سطحی و متعلق به یک ساختمان باشد.']);
                }
            }
            $category->title_key = \App\Services\Finance\ExpenseCategoryService::titleKey($category->parent_id, $category->title);
        });
        static::deleting(function (self $category): void {
            if ($category->type === FinancialCategoryType::Expense) {
                throw \Illuminate\Validation\ValidationException::withMessages(['category' => 'حذف دسته هزینه مجاز نیست؛ آن را غیرفعال کنید.']);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeUsableExpense(\Illuminate\Database\Eloquent\Builder $query, ?int $buildingId = null): void
    {
        $query->where('type', 'expense')->where('is_active', true)->whereNotNull('building_id')
            ->when($buildingId !== null, fn ($q) => $q->where('building_id', $buildingId))
            ->whereHas('parent', fn ($q) => $q->whereNull('parent_id')->where('type', 'expense')->where('is_active', true));
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => FinancialCategoryType::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function chargeItems(): HasMany
    {
        return $this->hasMany(ChargeItem::class, 'financial_category_id');
    }

    public function buildingExpenses(): HasMany
    {
        return $this->hasMany(BuildingExpense::class, 'financial_category_id');
    }

    public function buildingIncomes(): HasMany
    {
        return $this->hasMany(BuildingIncome::class, 'financial_category_id');
    }
}
