<?php

namespace App\Actions\SupportTicket;

use App\Models\SupportTicket;
use Illuminate\Support\Facades\DB;

class UpdateSupportTicket
{
    public function execute(SupportTicket $model, array $data): SupportTicket
    {
        return DB::transaction(function () use ($model, $data): SupportTicket {
            $model->update($data);
            return $model->refresh();
        });
    }
}
