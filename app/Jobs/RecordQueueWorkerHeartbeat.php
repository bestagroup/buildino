<?php

namespace App\Jobs;

use App\Services\System\RuntimeHeartbeatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordQueueWorkerHeartbeat implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public int $uniqueFor = 240;

    public function __construct(
        public readonly string $queueName
    ) {
        $this->onQueue(
            $queueName
        );
    }

    public function uniqueId(): string
    {
        return $this->queueName;
    }

    public function handle(
        RuntimeHeartbeatService $heartbeats
    ): void {
        $heartbeats->touch(
            'queue-worker:'
                .$this->queueName,
            [
                'queue' =>
                    $this->queueName,
                'connection' =>
                    config('queue.default'),
            ]
        );
    }
}
