<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reports:cleanup')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();
