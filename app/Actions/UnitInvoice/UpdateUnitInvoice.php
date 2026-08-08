<?php

namespace App\Actions\UnitInvoice;

use App\Models\UnitInvoice;
use Illuminate\Support\Facades\DB;

class UpdateUnitInvoice
{
    public function execute(UnitInvoice $model, array $data): UnitInvoice
    {
        return DB::transaction(function () use ($model, $data): UnitInvoice {
            $model->update($data);
            return $model->refresh();
        });
    }
}
