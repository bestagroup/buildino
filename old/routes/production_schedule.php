<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command(
    'system:scheduler-heartbeat'
)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(
    'system:dispatch-queue-heartbeats'
)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
