<?php

namespace App\Console\Commands;

use App\Services\Briefings\BriefingScheduler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('briefings:dispatch')]
#[Description('Generate and queue daily and weekly briefings whose delivery time has arrived')]
class DispatchBriefings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BriefingScheduler $scheduler): int
    {
        $queued = $scheduler->run();

        $this->components->info("Queued {$queued} briefing(s).");

        return self::SUCCESS;
    }
}
