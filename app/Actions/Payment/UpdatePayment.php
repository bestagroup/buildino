<?php

namespace App\Actions\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class UpdatePayment
{
    public function execute(Payment $model, array $data): Payment
    {
        return DB::transaction(function () use ($model, $data): Payment {
            $model->update($data);
            return $model->refresh();
        });
    }
}
