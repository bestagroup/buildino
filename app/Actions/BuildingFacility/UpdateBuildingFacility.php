<?php

namespace App\Actions\BuildingFacility;

use App\Models\BuildingFacility;
use Illuminate\Support\Facades\DB;

class UpdateBuildingFacility
{
    public function execute(BuildingFacility $model, array $data): BuildingFacility
    {
        return DB::transaction(function () use ($model, $data): BuildingFacility {
            $model->update($data);
            return $model->refresh();
        });
    }
}
