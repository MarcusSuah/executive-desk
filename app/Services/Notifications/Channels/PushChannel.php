<?php

namespace App\Services\Notifications\Channels;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Notifications\DeliveryResult;
use App\Services\Notifications\OutboundMessage;
use App\Services\Notifications\Push\PushGateway;

class PushChannel implements DeliveryChannel
{
    public function __construct(private PushGateway $gateway) {}

    /**
     * Determine whether the channel is configured on this installation.
     */
    public function isConfigured(): bool
    {
        return $this->gateway->isConfigured();
    }

    /**
     * Send the notification to every device the user has registered.
     *
     * Expired subscriptions are removed. The attempt counts as sent when at least
     * one push service accepted the message.
     */
    public function send(User $user, OutboundMessage $message): DeliveryResult
    {
        if (! $this->gateway->isConfigured()) {
            return DeliveryResult::unavailable('Web Push (VAPID) keys are not configured.');
        }

        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return DeliveryResult::unavailable('The recipient has no registered push devices.');
        }

        $payload = [
            'title' => $message->title,
            'body' => $message->body,
            'url' => $message->url,
            'tag' => $message->tag,
        ];

        $accepted = [];
        $errors = [];
        $retryable = false;

        foreach ($subscriptions as $subscription) {
            $outcome = $this->gateway->send($subscription, $payload);

            if ($outcome->success) {
                $subscription->forceFill(['last_used_at' => now()])->save();
                $accepted[] = $outcome->messageId ?? (string) $subscription->id;

                continue;
            }

            if ($outcome->expired) {
                $subscription->delete();

                continue;
            }

            $errors[] = $outcome->reason;
            $retryable = $retryable || $outcome->retryable;
        }

        if ($accepted !== []) {
            return DeliveryResult::sent(substr(implode(',', $accepted), 0, 250));
        }

        if ($errors === []) {
            return DeliveryResult::unavailable('All registered push devices have expired.');
        }

        $error = implode('; ', array_unique($errors));

        return $retryable ? DeliveryResult::transientFailure($error) : DeliveryResult::permanentFailure($error);
    }

    /**
     * Remove a subscription identified by endpoint (used when a browser unsubscribes).
     */
    public static function forgetEndpoint(User $user, string $endpoint): void
    {
        $user->pushSubscriptions()->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))->delete();
    }
}
