<?php

namespace App\Actions\SupportTicket;

use App\Models\SupportTicket;
use Illuminate\Support\Facades\DB;

class CreateSupportTicket
{
    public function execute(array $data): SupportTicket
    {
        return DB::transaction(fn (): SupportTicket => SupportTicket::query()->create($data));
    }
}
