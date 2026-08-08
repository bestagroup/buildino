<?php

namespace App\Actions\Announcement;

use App\Models\Announcement;
use Illuminate\Support\Facades\DB;

class CreateAnnouncement
{
    public function execute(array $data): Announcement
    {
        return DB::transaction(fn (): Announcement => Announcement::query()->create($data));
    }
}
