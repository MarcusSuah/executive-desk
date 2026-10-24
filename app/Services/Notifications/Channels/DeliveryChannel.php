<?php

namespace App\Services\Notifications\Channels;

use App\Models\User;
use App\Services\Notifications\DeliveryResult;
use App\Services\Notifications\OutboundMessage;

interface DeliveryChannel
{
    /**
     * Determine whether the channel is configured on this installation.
     */
    public function isConfigured(): bool;

    /**
     * Attempt delivery of the message to the user. Implementations must not throw for
     * provider errors; they return a failed or unavailable result instead.
     */
    public function send(User $user, OutboundMessage $message): DeliveryResult;
}
