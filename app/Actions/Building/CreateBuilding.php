<?php

namespace App\Actions\Building;

use App\Models\Building;
use Illuminate\Support\Facades\DB;

class CreateBuilding
{
    public function execute(array $data): Building
    {
        return DB::transaction(fn (): Building => Building::query()->create($data));
    }
}
