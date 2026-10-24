<?php

namespace App\Console\Commands;

use App\Services\Notifications\DigestScheduler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:send-digests')]
#[Description('Queue the email digests whose delivery time has arrived')]
class SendDigests extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DigestScheduler $scheduler): int
    {
        $queued = $scheduler->run();

        $this->components->info("Queued {$queued} digest(s).");

        return self::SUCCESS;
    }
}
