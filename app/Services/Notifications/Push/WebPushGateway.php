<?php

namespace App\Services\Notifications\Push;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Standards-based Web Push delivery signed with the application's VAPID keys.
 */
class WebPushGateway implements PushGateway
{
    private ?WebPush $client = null;

    /**
     * Determine whether VAPID keys are configured.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key'))
            && filled(config('services.webpush.private_key'))
            && filled(config('services.webpush.subject'));
    }

    /**
     * Send a JSON payload to a single browser subscription.
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(PushSubscription $subscription, array $payload): PushOutcome
    {
        try {
            $report = $this->client()->sendOneNotification(
                new Subscription(
                    $subscription->endpoint,
                    $subscription->public_key,
                    $subscription->auth_token,
                    $subscription->content_encoding,
                ),
                json_encode($payload, JSON_THROW_ON_ERROR),
                ['TTL' => config('services.webpush.ttl'), 'urgency' => 'high'],
            );
        } catch (Throwable $exception) {
            return PushOutcome::failed('Push request error: '.class_basename($exception), retryable: true);
        }

        if ($report->isSuccess()) {
            return PushOutcome::accepted($report->getResponse()?->getHeaderLine('Location') ?: null);
        }

        if ($report->isSubscriptionExpired()) {
            return PushOutcome::expired();
        }

        $status = $report->getResponse()?->getStatusCode();

        return PushOutcome::failed(
            'Push service responded with HTTP '.($status ?? 'no response'),
            retryable: $status === null || $status === 429 || $status >= 500,
        );
    }

    /**
     * Get the configured Web Push client.
     */
    private function client(): WebPush
    {
        return $this->client ??= new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ]);
    }
}
