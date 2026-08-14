<?php

namespace App\Actions\BuildingIncome;

use App\Models\BuildingIncome;
use Illuminate\Support\Facades\DB;

class CreateBuildingIncome
{
    public function execute(array $data): BuildingIncome
    {
        return DB::transaction(fn (): BuildingIncome => BuildingIncome::query()->create($data));
    }
}
