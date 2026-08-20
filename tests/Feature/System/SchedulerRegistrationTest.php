<?php

namespace Tests\Feature\System;

use App\Jobs\RecordQueueWorkerHeartbeat;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SchedulerRegistrationTest extends TestCase
{
    public function test_guest_visit_expiration_is_scheduled_exactly_once(): void
    {
        $matches = collect(
            $this->app->make(Schedule::class)->events()
        )->filter(
            fn ($event): bool => str_contains(
                (string) $event->command,
                'domain:expire-guest-visits'
            )
        );

        $this->assertCount(1, $matches);
    }

    public function test_runtime_heartbeat_commands_are_scheduled_exactly_once(): void
    {
        $commands = collect(
            $this->app->make(Schedule::class)->events()
        )->map(
            fn ($event): string => (string) $event->command
        );

        $this->assertCount(
            1,
            $commands->filter(
                fn (string $command): bool => str_contains(
                    $command,
                    'system:scheduler-heartbeat'
                )
            )
        );

        $this->assertCount(
            1,
            $commands->filter(
                fn (string $command): bool => str_contains(
                    $command,
                    'system:dispatch-queue-heartbeats'
                )
            )
        );
    }

    public function test_queue_heartbeat_command_dispatches_a_job_to_every_required_queue(): void
    {
        Queue::fake();

        $this->artisan(
            'system:dispatch-queue-heartbeats'
        )->assertSuccessful();

        foreach (
            config(
                'production_readiness.health.required_queues',
                ['default']
            )
            as $queue
        ) {
            Queue::assertPushedOn(
                (string) $queue,
                RecordQueueWorkerHeartbeat::class
            );
        }
    }

    public function test_local_runtime_scripts_start_scheduler_and_all_required_workers(): void
    {
        $composer = json_decode(
            (string) file_get_contents(base_path('composer.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        foreach (['dev', 'runtime'] as $scriptName) {
            $script = implode(
                ' ',
                data_get(
                    $composer,
                    "scripts.{$scriptName}",
                    []
                )
            );

            $this->assertStringContainsString(
                'artisan schedule:work',
                $script
            );

            foreach (
                config(
                    'production_readiness.health.required_queues',
                    ['default']
                )
                as $queue
            ) {
                $this->assertStringContainsString(
                    "artisan queue:work --queue={$queue}",
                    $script
                );
            }
        }
    }
}
