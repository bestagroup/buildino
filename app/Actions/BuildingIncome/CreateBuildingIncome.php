<?php

namespace App\Actions\BuildingIncome;

use App\Models\BuildingIncome;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateBuildingIncome
{
    public function execute(
        array $data,
        User $actor
    ): BuildingIncome
    {
        return DB::transaction(
            fn (): BuildingIncome =>
                BuildingIncome::query()->create([
                    ...$data,
                    'created_by' => $actor->getKey(),
                ])
        );
    }
}
