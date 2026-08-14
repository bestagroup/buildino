<?php

namespace App\Console\Commands;

use App\Services\System\RuntimeHeartbeatService;
use Illuminate\Console\Command;

class RecordSchedulerHeartbeat extends Command
{
    protected $signature =
        'system:scheduler-heartbeat';

    protected $description =
        'Record the Laravel scheduler heartbeat';

    public function handle(
        RuntimeHeartbeatService $heartbeats
    ): int {
        $heartbeats->touch(
            'scheduler',
            [
                'environment' =>
                    app()->environment(),
            ]
        );

        return self::SUCCESS;
    }
}
