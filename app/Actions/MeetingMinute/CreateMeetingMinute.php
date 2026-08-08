<?php

namespace App\Actions\MeetingMinute;

use App\Models\MeetingMinute;
use Illuminate\Support\Facades\DB;

class CreateMeetingMinute
{
    public function execute(array $data): MeetingMinute
    {
        return DB::transaction(fn (): MeetingMinute => MeetingMinute::query()->create($data));
    }
}
