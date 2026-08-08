<?php

namespace App\Actions\UnitInvoice;

use App\Models\UnitInvoice;
use Illuminate\Support\Facades\DB;

class CreateUnitInvoice
{
    public function execute(array $data): UnitInvoice
    {
        return DB::transaction(fn (): UnitInvoice => UnitInvoice::query()->create($data));
    }
}
