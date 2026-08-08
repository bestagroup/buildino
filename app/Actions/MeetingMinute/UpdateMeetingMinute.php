<?php

namespace App\Actions\MeetingMinute;

use App\Models\MeetingMinute;
use Illuminate\Support\Facades\DB;

class UpdateMeetingMinute
{
    public function execute(MeetingMinute $model, array $data): MeetingMinute
    {
        return DB::transaction(function () use ($model, $data): MeetingMinute {
            $model->update($data);
            return $model->refresh();
        });
    }
}
