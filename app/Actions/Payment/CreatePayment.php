<?php

namespace App\Actions\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class CreatePayment
{
    public function execute(array $data): Payment
    {
        return DB::transaction(fn (): Payment => Payment::query()->create($data));
    }
}
