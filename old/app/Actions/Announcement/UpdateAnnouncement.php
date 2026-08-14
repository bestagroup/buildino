<?php

namespace App\Actions\Announcement;

use App\Models\Announcement;
use Illuminate\Support\Facades\DB;

class UpdateAnnouncement
{
    public function execute(Announcement $model, array $data): Announcement
    {
        return DB::transaction(function () use ($model, $data): Announcement {
            $model->update($data);
            return $model->refresh();
        });
    }
}
