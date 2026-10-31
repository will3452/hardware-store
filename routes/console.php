<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:work --stop-when-empty --max-time=45 --timeout=30 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->name('database-queue-drain');

Schedule::command('app:backup')->dailyAt('02:00')->timezone('Asia/Manila')->withoutOverlapping(60);
