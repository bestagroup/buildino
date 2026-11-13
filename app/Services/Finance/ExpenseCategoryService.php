<?php

namespace App\Services\Finance;

use App\Models\Building;
use App\Models\FinancialCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ExpenseCategoryService
{
    public static function normalize(string $title): string
    {
        return trim(preg_replace('/[\s\x{200C}]+/u', ' ', str_replace(['ي', 'ك'], ['ی', 'ک'], $title)));
    }

    public static function titleKey(?int $parentId, string $title): string
    {
        return hash('sha256', ($parentId ?? 0).'|'.mb_strtolower(self::normalize($title)));
    }

    public function seed(Building $building): void
    {
        DB::transaction(function () use ($building) {
            Building::withTrashed()->whereKey($building->id)->lockForUpdate()->firstOrFail();
            foreach (config('expense_categories') as $key => [$title, $children]) {
                $parent = $this->default($building->id, null, $key, $title);
                foreach ($children as $index => $child) {
                    $this->default($building->id, $parent->id, $key.'.'.($index + 1), $child);
                }
            }
        }, 3);
    }

    private function default(int $buildingId, ?int $parentId, string $key, string $title): FinancialCategory
    {
        $existing = FinancialCategory::where('building_id', $buildingId)->where('default_key', $key)->first();
        if ($existing) return $existing; // Never reset a manager's title or activation choice.
        $existing = FinancialCategory::where('building_id', $buildingId)->where('type', 'expense')
            ->where('title_key', self::titleKey($parentId, $title))->first();
        if ($existing) {
            $existing->update(['default_key' => $key]);
            return $existing;
        }
        return FinancialCategory::create(['building_id' => $buildingId, 'parent_id' => $parentId,
            'default_key' => $key, 'title' => $title, 'type' => 'expense', 'is_active' => true]);
    }

    public function save(Building $building, array $data, ?FinancialCategory $category = null): FinancialCategory
    {
        return DB::transaction(function () use ($building, $data, $category) {
            Building::whereKey($building->id)->lockForUpdate()->firstOrFail();
            if ($category) $category = FinancialCategory::whereKey($category->id)->lockForUpdate()->firstOrFail();
            $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
            if ($category && $category->parent_id !== $parentId) {
                throw ValidationException::withMessages(['parent_id' => 'سطح و دسته اصلی رکورد موجود قابل تغییر نیست؛ عنوان یا وضعیت را ویرایش کنید.']);
            }
            $parent = $parentId ? FinancialCategory::whereKey($parentId)->where('building_id', $building->id)
                ->where('type', 'expense')->whereNull('parent_id')->first() : null;
            if ($parentId && (! $parent || $parent->id === $category?->id)) {
                throw ValidationException::withMessages(['parent_id' => 'دسته اصلی باید متعلق به همین ساختمان باشد؛ سطح سوم مجاز نیست.']);
            }
            $title = trim($data['title']);
            if (self::normalize($title) === '' || FinancialCategory::where('building_id', $building->id)->where('type', 'expense')
                ->where('title_key', self::titleKey($parentId, $title))->when($category, fn ($q) => $q->whereKeyNot($category->id))->exists()) {
                throw ValidationException::withMessages(['title' => 'عنوان خالی یا تکراری در این دسته مجاز نیست.']);
            }
            $category ??= new FinancialCategory(['building_id' => $building->id, 'type' => 'expense']);
            $category->fill(['title' => $title, 'parent_id' => $parentId, 'is_active' => $data['is_active'] ?? true])->save();
            return $category->load('parent');
        }, 3);
    }

    public function requireUsable(int $buildingId, int $categoryId): FinancialCategory
    {
        $category = FinancialCategory::usableExpense($buildingId)->find($categoryId);
        if (! $category) {
            throw ValidationException::withMessages(['financial_category_id' => 'زیر دسته فعال و متعلق به همین ساختمان را انتخاب کنید؛ دسته اصلی نیز باید فعال باشد.']);
        }
        return $category;
    }
}
