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


Schedule::command(
    'notifications:prune-stale-devices'
)
    ->dailyAt('03:40')
    ->withoutOverlapping()
    ->onOneServer();
