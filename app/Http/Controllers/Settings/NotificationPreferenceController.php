<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationPreferenceRequest;
use App\Http\Resources\NotificationDeliveryResource;
use App\Models\PushSubscription;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationSender;
use App\Services\Notifications\Sms\SmsGateway;
use App\Services\Reminders\ReminderPlanner;
use App\Support\FormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    /**
     * Show the notification settings page.
     */
    public function edit(Request $request, NotificationSender $sender, SmsGateway $sms): Response
    {
        $user = $request->user();
        $preferences = $user->preferences();

        return Inertia::render('settings/notifications', [
            'preferences' => [
                ...Arr::except($preferences->toArray(), ['id', 'user_id', 'created_at', 'updated_at']),
                'daily_briefing_time' => substr($preferences->daily_briefing_time, 0, 5),
                'weekly_briefing_time' => substr($preferences->weekly_briefing_time, 0, 5),
            ],
            'contact' => [
                'email' => $user->email,
                'email_verified' => $user->email_verified_at !== null,
                'phone' => $user->phone,
                'phone_verified' => $user->hasVerifiedPhone(),
            ],
            'channels' => [
                'mail' => ['configured' => $sender->channel(NotificationChannel::Mail)->isConfigured(), 'detail' => config('mail.default')],
                'sms' => ['configured' => $sms->isConfigured(), 'detail' => $sms->name()],
                'push' => ['configured' => $sender->channel(NotificationChannel::Push)->isConfigured(), 'detail' => null],
            ],
            'devices' => $user->pushSubscriptions()
                ->latest()
                ->get()
                ->map(fn (PushSubscription $subscription) => [
                    'id' => $subscription->id,
                    'device_name' => $subscription->device_name,
                    'created_at' => $subscription->created_at?->toIso8601ZuluString(),
                    'last_used_at' => $subscription->last_used_at?->toIso8601ZuluString(),
                ]),
            'recentDeliveries' => NotificationDeliveryResource::collection(
                $user->notificationDeliveries()->latest('attempted_at')->limit(10)->get(),
            ),
            'reminderMinutes' => FormOptions::reminderMinutes(),
        ]);
    }

    /**
     * Save the notification settings and re-plan the user's upcoming reminders.
     */
    public function update(NotificationPreferenceRequest $request, ReminderPlanner $planner, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $preferences = $user->preferences();

        $preferences->fill($request->preferenceAttributes())->save();
        $audit->logChanges('notification_preferences.updated', $preferences, $user);

        $planner->syncForUser($user->setRelation('notificationPreference', $preferences));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification settings saved.')]);

        return to_route('notifications.edit');
    }
}
