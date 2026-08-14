<?php

namespace App\Console\Commands;

use App\Jobs\RecordQueueWorkerHeartbeat;
use Illuminate\Console\Command;

class DispatchQueueHeartbeats extends Command
{
    protected $signature =
        'system:dispatch-queue-heartbeats';

    protected $description =
        'Dispatch heartbeat jobs to required queues so worker liveness can be measured';

    public function handle(): int
    {
        $queues = config(
            'production_readiness.health.required_queues',
            ['default']
        );

        foreach ($queues as $queue) {
            RecordQueueWorkerHeartbeat::dispatch(
                (string) $queue
            );
        }

        return self::SUCCESS;
    }
}
