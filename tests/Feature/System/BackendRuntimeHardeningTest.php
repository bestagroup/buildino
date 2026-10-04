<?php

namespace Tests\Feature\System;

use App\Jobs\Notifications\SendUserNotificationJob;
use App\Jobs\Reports\GenerateReportJob;
use Tests\TestCase;

class BackendRuntimeHardeningTest extends TestCase
{
    public function test_queue_retry_leases_exceed_job_timeouts(): void
    {
        $report = new GenerateReportJob(1);

        $notification = new SendUserNotificationJob(
            1,
            new \App\Data\Notifications\NotificationMessage(
                type: 'runtime-test',
                title: 'Runtime test',
                message: 'Runtime test'
            ),
            'database',
            'runtime-hardening-test'
        );

        foreach ([
            'database',
            'redis',
            'beanstalkd',
        ] as $connection) {
            $retryAfter = (int) config(
                "queue.connections.{$connection}.retry_after"
            );

            $this->assertGreaterThan(
                $report->timeout,
                $retryAfter,
                "{$connection} retry_after must exceed report timeout."
            );

            $this->assertGreaterThan(
                $notification->timeout,
                $retryAfter,
                "{$connection} retry_after must exceed notification timeout."
            );
        }
    }
}
