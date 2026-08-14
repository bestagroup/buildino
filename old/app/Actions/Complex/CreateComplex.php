<?php

namespace App\Actions\Complex;

use App\Models\Complex;
use Illuminate\Support\Facades\DB;

class CreateComplex
{
    public function execute(array $data): Complex
    {
        return DB::transaction(fn (): Complex => Complex::query()->create($data));
    }
}
