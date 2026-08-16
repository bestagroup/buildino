<?php

namespace Tests\Feature\System;

use Illuminate\Console\Scheduling\Schedule;
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
}
