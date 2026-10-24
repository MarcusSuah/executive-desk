<?php

namespace App\Console\Commands;

use App\Services\Tasks\RecurringTaskGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tasks:generate-recurring')]
#[Description('Generate upcoming occurrences of recurring tasks')]
class GenerateRecurringTasks extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RecurringTaskGenerator $generator): int
    {
        $created = $generator->generateAll();

        $this->components->info("Generated {$created} task occurrence(s).");

        return self::SUCCESS;
    }
}
