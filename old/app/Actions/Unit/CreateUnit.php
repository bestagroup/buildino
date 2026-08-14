<?php

namespace App\Actions\Unit;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class CreateUnit
{
    public function execute(array $data): Unit
    {
        return DB::transaction(fn (): Unit => Unit::query()->create($data));
    }
}
