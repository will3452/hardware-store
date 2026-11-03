<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => Cache::put('scheduler:last_seen', now()->utc()->toIso8601String(), now()->addDays(7)))
    ->everyMinute()->name('scheduler-heartbeat');

Schedule::command('queue:work --stop-when-empty --max-time=45 --timeout=30 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->name('database-queue-drain');

Schedule::command('app:backup')->dailyAt('02:00')->timezone('Asia/Manila')->withoutOverlapping(60);

Schedule::command('app:mail-drain --limit=10 --max-time=35')->everyMinute()->withoutOverlapping(5)->name('email-outbox-drain');
