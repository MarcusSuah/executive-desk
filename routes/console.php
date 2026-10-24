<?php

use App\Services\SystemHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:dispatch')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command('briefings:dispatch')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::command('tasks:generate-recurring')
    ->hourly()
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('queue:prune-failed --hours=720')->daily();

Schedule::call(fn () => Cache::put(SystemHealth::SCHEDULER_HEARTBEAT_KEY, now()->toIso8601String(), now()->addHour()))
    ->name('scheduler-heartbeat')
    ->everyMinute();
