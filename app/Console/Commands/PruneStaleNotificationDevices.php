<?php

namespace App\Console\Commands;

use App\Models\UserDevice;
use Illuminate\Console\Command;

final class PruneStaleNotificationDevices extends Command
{
    protected $signature =
        'notifications:prune-stale-devices
        {--days= : Days since last activity before a push token is considered stale}
        {--dry-run : Report only; do not clear tokens}';

    protected $description =
        'Clear push tokens from stale notification device registrations';

    public function handle(): int
    {
        $days =
            max(
                1,
                (int) (
                    $this->option(
                        'days'
                    )
                    ?: config(
                        'mobile.device_stale_days',
                        35
                    )
                )
            );

        $cutoff =
            now()->subDays(
                $days
            );

        $query =
            UserDevice::query()
                ->whereNotNull(
                    'push_token'
                )
                ->where(
                    function ($query) use ($cutoff): void {
                        $query
                            ->where(
                                'last_used_at',
                                '<',
                                $cutoff
                            )
                            ->orWhere(
                                function ($query) use ($cutoff): void {
                                    $query
                                        ->whereNull(
                                            'last_used_at'
                                        )
                                        ->where(
                                            'updated_at',
                                            '<',
                                            $cutoff
                                        );
                                }
                            );
                    }
                );

        $count =
            (clone $query)
                ->count();

        $this->table(
            [
                'Check',
                'Value',
            ],
            [
                [
                    'Stale threshold',
                    "{$days} days",
                ],
                [
                    'Cutoff',
                    $cutoff
                        ->toDateTimeString(),
                ],
                [
                    'Push tokens matched',
                    $count,
                ],
                [
                    'Mode',
                    $this->option(
                        'dry-run'
                    )
                        ? 'DRY RUN'
                        : 'UPDATE',
                ],
            ]
        );

        if (
            $count === 0
            || $this->option(
                'dry-run'
            )
        ) {
            return self::SUCCESS;
        }

        $updated =
            $query->update([
                'push_token' =>
                    null,
            ]);

        $this->info(
            "Cleared {$updated} stale push token(s)."
        );

        return self::SUCCESS;
    }
}
