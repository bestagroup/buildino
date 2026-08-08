<?php

namespace App\Actions\Building;

use App\Models\Building;
use Illuminate\Support\Facades\DB;

class UpdateBuilding
{
    public function execute(Building $model, array $data): Building
    {
        return DB::transaction(function () use ($model, $data): Building {
            $model->update($data);
            return $model->refresh();
        });
    }
}
