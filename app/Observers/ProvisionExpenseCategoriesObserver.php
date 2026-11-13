<?php

namespace App\Observers;

use App\Models\Building;
use App\Services\Finance\ExpenseCategoryService;

final class ProvisionExpenseCategoriesObserver
{
    public function __construct(private readonly ExpenseCategoryService $categories) {}

    public function created(Building $building): void
    {
        $this->categories->seed($building);
    }
}
