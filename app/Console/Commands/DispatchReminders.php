<?php

namespace App\Console\Commands;

use App\Services\Reminders\ReminderDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:dispatch')]
#[Description('Queue due reminders, skip missed ones, and recover reminders stuck in flight')]
class DispatchReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ReminderDispatcher $dispatcher): int
    {
        $result = $dispatcher->run();

        $this->components->info(sprintf(
            'Dispatched %d, recovered %d, interrupted %d, skipped %d missed reminder(s).',
            $result['dispatched'],
            $result['recovered'],
            $result['interrupted'],
            $result['missed'],
        ));

        return self::SUCCESS;
    }
}
