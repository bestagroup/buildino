<?php

namespace App\Actions\ServiceRequest;

use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;

class CreateServiceRequest
{
    public function execute(array $data): ServiceRequest
    {
        return DB::transaction(fn (): ServiceRequest => ServiceRequest::query()->create($data));
    }
}
