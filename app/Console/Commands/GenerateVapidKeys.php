<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

#[Signature('push:vapid')]
#[Description('Generate a VAPID key pair for Web Push and print the .env lines')]
class GenerateVapidKeys extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->components->warn('Add these lines to .env. Keep the private key secret; changing keys invalidates existing browser subscriptions.');
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
