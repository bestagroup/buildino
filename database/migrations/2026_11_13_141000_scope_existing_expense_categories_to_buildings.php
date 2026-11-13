<?php

use App\Models\Building;
use App\Services\Finance\ExpenseCategoryService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Keep global originals for historical JSON references. Never merge categories or formulas.
        $global = DB::table('financial_categories')->where('type', 'expense')->whereNull('building_id')->get();
        Building::withTrashed()->orderBy('id')->each(function (Building $building) use ($global): void {
            DB::transaction(function () use ($building, $global): void {
                DB::table('buildings')->where('id', $building->id)->lockForUpdate()->first();
                $local = DB::table('financial_categories')->where('building_id', $building->id)
                    ->where('type', 'expense')->whereNull('parent_id')->whereNull('default_key')->get();
                if ($global->isNotEmpty() || $local->isNotEmpty()) {
                    $parentId = DB::table('financial_categories')->where('building_id', $building->id)->where('default_key', 'legacy')->value('id');
                    if (! $parentId) $parentId = DB::table('financial_categories')->insertGetId([
                        'building_id' => $building->id, 'title' => 'دسته‌های پیشین', 'type' => 'expense', 'is_active' => true,
                        'default_key' => 'legacy', 'title_key' => ExpenseCategoryService::titleKey(null, 'دسته‌های پیشین'),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    foreach ($local as $category) {
                        $title = $this->uniqueTitle($building->id, $parentId, $category->title, $category->id);
                        DB::table('financial_categories')->where('id', $category->id)->update([
                            'parent_id' => $parentId, 'title' => $title, 'title_key' => ExpenseCategoryService::titleKey($parentId, $title),
                        ]);
                    }
                    foreach ($global as $category) {
                        $key = 'legacy.'.$category->id;
                        $id = DB::table('financial_categories')->where('building_id', $building->id)->where('default_key', $key)->value('id');
                        if (! $id) {
                            $title = $this->uniqueTitle($building->id, $parentId, $category->title, $category->id);
                            $id = DB::table('financial_categories')->insertGetId([
                                'building_id' => $building->id, 'parent_id' => $parentId, 'title' => $title,
                                'type' => 'expense', 'is_active' => $category->is_active, 'default_key' => $key,
                                'title_key' => ExpenseCategoryService::titleKey($parentId, $title), 'created_at' => now(), 'updated_at' => now(),
                            ]);
                        }
                        foreach (['building_expenses', 'building_incomes', 'building_expense_allocation_rules'] as $table) {
                            DB::table($table)->where('building_id', $building->id)->where('financial_category_id', $category->id)->update(['financial_category_id' => $id]);
                        }
                        DB::table('charge_items')->where('financial_category_id', $category->id)
                            ->whereIn('charge_formula_id', DB::table('charge_formulas')->where('building_id', $building->id)->select('id'))
                            ->update(['financial_category_id' => $id]);
                    }
                }
                app(ExpenseCategoryService::class)->seed($building);
            }, 3);
        });
    }

    private function uniqueTitle(int $buildingId, int $parentId, string $title, int $oldId): string
    {
        $base = $title;
        $suffix = 0;
        while (DB::table('financial_categories')->where('building_id', $buildingId)->where('type', 'expense')
            ->where('title_key', ExpenseCategoryService::titleKey($parentId, $title))->exists()) {
            $title = mb_substr($base, 0, 220).' ('.$oldId.(++$suffix > 1 ? '-'.$suffix : '').')';
        }
        return $title;
    }

    public function down(): void
    {
        // Reversing tenant ownership would lose manager edits and merge separate histories.
    }
};
