<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Services\Finance\ExpenseCategoryService;
use Illuminate\Database\Seeder;

final class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        Building::query()->orderBy('id')->each(fn (Building $building) => app(ExpenseCategoryService::class)->seed($building));
    }
}
