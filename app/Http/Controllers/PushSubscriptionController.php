<?php

namespace App\Http\Controllers;

use App\Enums\NotificationChannel;
use App\Mail\NoticeMail;
use App\Models\PushSubscription;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationSender;
use App\Services\Notifications\OutboundMessage;
use App\Services\Reminders\ReminderPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PushSubscriptionController extends Controller
{
    /**
     * Register this browser for push notifications and turn push on for the user.
     */
    public function store(Request $request, ReminderPlanner $planner, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);

        $user = $request->user();
        $hash = PushSubscription::hashEndpoint($validated['endpoint']);

        DB::transaction(function () use ($validated, $hash, $user, $request, $audit): void {
            // A browser endpoint belongs to whoever is signed in on it now.
            PushSubscription::query()->where('endpoint_hash', $hash)->where('user_id', '!=', $user->id)->delete();

            $subscription = $user->pushSubscriptions()->updateOrCreate(
                ['endpoint_hash' => $hash],
                [
                    'endpoint' => $validated['endpoint'],
                    'public_key' => $validated['keys']['p256dh'],
                    'auth_token' => $validated['keys']['auth'],
                    'content_encoding' => $validated['content_encoding'] ?? 'aes128gcm',
                    'device_name' => $this->deviceName((string) $request->userAgent()),
                    'user_agent' => Str::limit((string) $request->userAgent(), 490),
                ],
            );

            $user->preferences()->update(['push_enabled' => true]);

            $audit->log('push.subscribed', $user, [], ['subscription_id' => $subscription->id, 'device' => $subscription->device_name], $user);
        });

        $planner->syncForUser($user->refresh());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Push notifications are on for this device.')]);

        return back();
    }

    /**
     * Remove one of the user's registered devices.
     */
    public function destroy(Request $request, PushSubscription $subscription, ReminderPlanner $planner, AuditLogger $audit): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);

        $this->forget($request, $subscription, $planner, $audit);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Device removed.')]);

        return back();
    }

    /**
     * Remove the subscription for this browser's endpoint (when the user turns push off here).
     */
    public function unsubscribe(Request $request, ReminderPlanner $planner, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);

        $subscription = $request->user()->pushSubscriptions()
            ->where('endpoint_hash', PushSubscription::hashEndpoint($validated['endpoint']))
            ->first();

        if ($subscription !== null) {
            $this->forget($request, $subscription, $planner, $audit);
        }

        return back();
    }

    /**
     * Send a test notification to every registered device.
     */
    public function test(Request $request, NotificationSender $sender): RedirectResponse
    {
        $user = $request->user();

        $result = $sender->send($user, NotificationChannel::Push, new OutboundMessage(
            type: 'test',
            title: 'Executive Desk test notification',
            body: 'Push notifications are working on this device.',
            mailable: new NoticeMail('Test', 'Test', 'Test'),
            smsText: '',
            url: route('notifications.edit'),
            tag: 'test',
        ));

        Inertia::flash('toast', [
            'type' => $result->wasSent() ? 'success' : 'error',
            'message' => $result->wasSent()
                ? __('Test notification accepted by the push service. Delivery to the device depends on the browser and operating system.')
                : __('Push test failed: :reason', ['reason' => (string) $result->error]),
        ]);

        return back();
    }

    /**
     * Delete a subscription and turn push off when no devices remain.
     */
    private function forget(Request $request, PushSubscription $subscription, ReminderPlanner $planner, AuditLogger $audit): void
    {
        $user = $request->user();

        $subscription->delete();

        if (! $user->pushSubscriptions()->exists()) {
            $user->preferences()->update(['push_enabled' => false]);
        }

        $audit->log('push.unsubscribed', $user, [], ['subscription_id' => $subscription->id], $user);
        $planner->syncForUser($user->refresh());
    }

    /**
     * Describe the device from its user agent, e.g. "Chrome on Windows".
     */
    private function deviceName(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $platform = match (true) {
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'unknown device',
        };

        return "{$browser} on {$platform}";
    }
}
