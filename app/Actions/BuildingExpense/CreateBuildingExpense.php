<?php

namespace App\Actions\BuildingExpense;

use App\Models\BuildingExpense;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateBuildingExpense
{
    public function execute(
        array $data,
        User $actor
    ): BuildingExpense
    {
        return DB::transaction(
            fn (): BuildingExpense =>
                BuildingExpense::query()->create([
                    ...$data,
                    'created_by' => $actor->getKey(),
                ])
        );
    }
}
