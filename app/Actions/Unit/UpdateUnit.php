<?php

namespace App\Actions\Unit;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class UpdateUnit
{
    public function execute(Unit $model, array $data): Unit
    {
        return DB::transaction(function () use ($model, $data): Unit {
            $model->update($data);
            return $model->refresh();
        });
    }
}
