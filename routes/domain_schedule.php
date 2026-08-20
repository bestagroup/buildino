<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('domain:expire-unit-invitations')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('domain:expire-facility-reservations')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('domain:expire-guest-visits')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('notifications:invoice-reminders --days=1')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('notifications:reservation-reminders')
    ->dailyAt('18:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('invoices:mark-overdue-installments')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('loyalty:expire-points')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
