<?php

namespace App\Actions\BuildingFacility;

use App\Models\BuildingFacility;
use Illuminate\Support\Facades\DB;

class CreateBuildingFacility
{
    public function execute(array $data): BuildingFacility
    {
        return DB::transaction(fn (): BuildingFacility => BuildingFacility::query()->create($data));
    }
}
