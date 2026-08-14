<?php

namespace App\Actions\BuildingExpense;

use App\Models\BuildingExpense;
use Illuminate\Support\Facades\DB;

class UpdateBuildingExpense
{
    public function execute(BuildingExpense $model, array $data): BuildingExpense
    {
        return DB::transaction(function () use ($model, $data): BuildingExpense {
            $model->update($data);
            return $model->refresh();
        });
    }
}
