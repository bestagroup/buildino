<?php

namespace App\Actions\MeetingMinute;

use App\Models\MeetingMinute;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateMeetingMinute
{
    public function execute(
        array $data,
        User $actor
    ): MeetingMinute
    {
        return DB::transaction(
            fn (): MeetingMinute =>
                MeetingMinute::query()->create([
                    ...$data,
                    'created_by' => $actor->getKey(),
                ])
        );
    }
}
