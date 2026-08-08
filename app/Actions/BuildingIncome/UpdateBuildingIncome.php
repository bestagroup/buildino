<?php

namespace App\Actions\BuildingIncome;

use App\Models\BuildingIncome;
use Illuminate\Support\Facades\DB;

class UpdateBuildingIncome
{
    public function execute(BuildingIncome $model, array $data): BuildingIncome
    {
        return DB::transaction(function () use ($model, $data): BuildingIncome {
            $model->update($data);
            return $model->refresh();
        });
    }
}
