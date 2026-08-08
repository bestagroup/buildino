<?php

namespace App\Actions\ServiceRequest;

use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;

class UpdateServiceRequest
{
    public function execute(ServiceRequest $model, array $data): ServiceRequest
    {
        return DB::transaction(function () use ($model, $data): ServiceRequest {
            $model->update($data);
            return $model->refresh();
        });
    }
}
