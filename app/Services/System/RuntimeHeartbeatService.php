<?php

namespace App\Services\System;

use App\Models\SystemRuntimeHeartbeat;
use Illuminate\Support\Facades\Schema;

final class RuntimeHeartbeatService
{
    public function touch(
        string $name,
        array $metadata = []
    ): ?SystemRuntimeHeartbeat {
        if (
            ! Schema::hasTable(
                'system_runtime_heartbeats'
            )
        ) {
            return null;
        }

        return SystemRuntimeHeartbeat::query()
            ->updateOrCreate(
                [
                    'name' => $name,
                ],
                [
                    'last_seen_at' => now(),
                    'host' =>
                        gethostname()
                        ?: null,
                    'process_id' =>
                        getmypid()
                        ?: null,
                    'metadata' =>
                        $metadata,
                ]
            )
            ->refresh();
    }

    public function get(
        string $name
    ): ?SystemRuntimeHeartbeat {
        if (
            ! Schema::hasTable(
                'system_runtime_heartbeats'
            )
        ) {
            return null;
        }

        return SystemRuntimeHeartbeat::query()
            ->find($name);
    }

    public function ageSeconds(
        string $name
    ): ?int {
        $heartbeat = $this->get(
            $name
        );

        if (! $heartbeat?->last_seen_at) {
            return null;
        }

        return max(
            0,
            (int) $heartbeat
                ->last_seen_at
                ->diffInSeconds(
                    now()
                )
        );
    }
}
