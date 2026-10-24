<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:dispatch')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command('tasks:generate-recurring')
    ->hourly()
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('queue:prune-failed --hours=720')->daily();
