<?php

namespace App\Services\Notifications\Push;

use App\Models\PushSubscription;

interface PushGateway
{
    /**
     * Determine whether VAPID keys are configured.
     */
    public function isConfigured(): bool;

    /**
     * Send a JSON payload to a single browser subscription.
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(PushSubscription $subscription, array $payload): PushOutcome;
}
