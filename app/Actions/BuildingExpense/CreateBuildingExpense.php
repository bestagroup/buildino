<?php

namespace App\Actions\BuildingExpense;

use App\Models\BuildingExpense;
use Illuminate\Support\Facades\DB;

class CreateBuildingExpense
{
    public function execute(array $data): BuildingExpense
    {
        return DB::transaction(fn (): BuildingExpense => BuildingExpense::query()->create($data));
    }
}
