<?php
namespace Tests\Feature\Financial;

use App\Models\FinancialCategory;
use App\Services\Finance\ExpenseCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class ExpenseCategoryHierarchyTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_defaults_are_independent_idempotent_and_preserve_manager_edits(): void
    {
        $a = $this->createBuildingGraph()['building'];
        $b = $this->createBuildingGraph()['building'];
        $service = app(ExpenseCategoryService::class);
        $this->assertSame(7, $a->financialCategories()->whereNull('parent_id')->count());
        $expected = 7 + collect(config('expense_categories'))->sum(fn ($group) => count($group[1]));
        $this->assertSame($expected, $a->financialCategories()->count());
        $root = $a->financialCategories()->where('default_key', 'general')->firstOrFail();
        $service->save($a, ['title' => 'عنوان ویرایش شده', 'parent_id' => null, 'is_active' => false], $root);
        $service->seed($a);
        $this->assertSame($expected, $a->financialCategories()->count());
        $this->assertFalse($root->fresh()->is_active);
        $this->assertSame('عنوان ویرایش شده', $root->fresh()->title);
        $this->assertSame('مدیریت، مالی و امور عمومی', $b->financialCategories()->where('default_key', 'general')->value('title'));
        $this->assertSame(0, FinancialCategory::usableExpense($a->id)->where('parent_id', $root->id)->count());
    }

    public function test_third_level_cross_building_duplicates_and_deletion_are_rejected(): void
    {
        $a = $this->createBuildingGraph()['building'];
        $b = $this->createBuildingGraph()['building'];
        $service = app(ExpenseCategoryService::class);
        $root = $a->financialCategories()->where('default_key', 'general')->firstOrFail();
        $child = $a->financialCategories()->where('default_key', 'general.1')->firstOrFail();
        foreach ([$child->id, $b->financialCategories()->whereNull('parent_id')->first()->id] as $parent) {
            try { $service->save($a, ['title' => 'آزمایش', 'parent_id' => $parent]); $this->fail('Invalid parent accepted'); }
            catch (ValidationException) { $this->assertTrue(true); }
        }
        try { $service->save($a, ['title' => '  '.$child->title.'  ', 'parent_id' => $root->id]); $this->fail('Duplicate accepted'); }
        catch (ValidationException) { $this->assertTrue(true); }
        try { $child->delete(); $this->fail('Deletion accepted'); }
        catch (ValidationException) { $this->assertDatabaseHas('financial_categories', ['id' => $child->id]); }
        try { $service->requireUsable($b->id, $child->id); $this->fail('Foreign category accepted'); }
        catch (ValidationException) { $this->assertTrue(true); }
    }
}
