<?php

namespace App\Actions\Complex;

use App\Models\Complex;
use Illuminate\Support\Facades\DB;

class UpdateComplex
{
    public function execute(Complex $model, array $data): Complex
    {
        return DB::transaction(function () use ($model, $data): Complex {
            $model->update($data);
            return $model->refresh();
        });
    }
}
