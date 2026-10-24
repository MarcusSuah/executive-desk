<?php

use App\Enums\ReminderStatus;
use App\Models\CalendarEvent;
use App\Models\NotificationDelivery;
use App\Models\PushSubscription;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Reminders\ReminderPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-26 01:00:00', 'UTC'));
    $this->executive = User::factory()->executive()->create();
});

/**
 * Build a valid preferences payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function preferencePayload(array $overrides = []): array
{
    return [
        'email_enabled' => true,
        'sms_enabled' => false,
        'push_enabled' => false,
        'event_reminder_minutes' => [60, 15],
        'task_reminder_minutes' => [1440],
        'presentation_reminder_minutes' => [1440],
        'schedule_change_notifications' => true,
        'daily_briefing_enabled' => true,
        'daily_briefing_time' => '06:30',
        'daily_briefing_channels' => ['mail'],
        'weekly_briefing_enabled' => false,
        'weekly_briefing_day' => 1,
        'weekly_briefing_time' => '07:00',
        'weekly_briefing_channels' => [],
        ...$overrides,
    ];
}

describe('preferences', function () {
    it('saves preferences and re-plans upcoming reminders with the new intervals', function () {
        $event = CalendarEvent::factory()->for($this->executive, 'owner')->create(['starts_at' => '2026-10-27 09:00:00', 'ends_at' => '2026-10-27 10:00:00']);
        app(ReminderPlanner::class)->syncEvent($event);

        $this->actingAs($this->executive)
            ->put(route('notifications.update'), preferencePayload())
            ->assertRedirect(route('notifications.edit'));

        expect($this->executive->refresh()->preferences()->daily_briefing_time)->toBe('06:30:00')
            ->and(Reminder::where('status', ReminderStatus::Pending->value)->pluck('minutes_before')->sort()->values()->all())->toBe([15, 60]);
    });

    it('rejects an unsupported reminder interval', function () {
        $this->actingAs($this->executive)
            ->put(route('notifications.update'), preferencePayload(['event_reminder_minutes' => [7]]))
            ->assertSessionHasErrors('event_reminder_minutes.0');
    });

    it('refuses to enable SMS without a verified phone number', function () {
        $this->actingAs($this->executive)
            ->put(route('notifications.update'), preferencePayload(['sms_enabled' => true]))
            ->assertSessionHasErrors(['sms_enabled' => 'Verify your mobile number before turning on SMS notifications.']);
    });

    it('requires a channel when a briefing is enabled', function () {
        $this->actingAs($this->executive)
            ->put(route('notifications.update'), preferencePayload(['daily_briefing_channels' => []]))
            ->assertSessionHasErrors('daily_briefing_channels');
    });

    it('reports SMS as not configured on the settings page', function () {
        config(['services.sms.driver' => 'none']);

        $this->actingAs($this->executive)
            ->get(route('notifications.edit'))
            ->assertInertia(fn ($page) => $page
                ->component('settings/notifications')
                ->where('channels.sms.configured', false));
    });
});

describe('phone verification', function () {
    it('reports SMS as unavailable instead of pretending to send a code', function () {
        config(['services.sms.driver' => 'none']);

        $this->actingAs($this->executive)
            ->post(route('phone.send'), ['phone' => '+639171234567'])
            ->assertSessionHasErrors('phone');
    });

    it('verifies the number with the code sent by SMS', function () {
        config(['services.sms.driver' => 'log']);
        $sentCode = null;
        Log::listen(function ($message) use (&$sentCode) {
            if (preg_match('/code is (\d{6})/', (string) ($message->context['message'] ?? ''), $matches)) {
                $sentCode = $matches[1];
            }
        });

        $this->actingAs($this->executive)->post(route('phone.send'), ['phone' => '+63 917 123 4567'])->assertSessionHasNoErrors();
        $this->actingAs($this->executive)->post(route('phone.verify'), ['code' => $sentCode])->assertSessionHasNoErrors();

        expect($this->executive->refresh()->phone)->toBe('+639171234567')
            ->and($this->executive->phone_verified_at)->not->toBeNull();
    });

    it('rejects a wrong code without verifying the number', function () {
        config(['services.sms.driver' => 'log']);

        $this->actingAs($this->executive)->post(route('phone.send'), ['phone' => '+639171234567']);
        $this->actingAs($this->executive)
            ->post(route('phone.verify'), ['code' => '000000'])
            ->assertSessionHasErrors(['code' => 'The code is incorrect.']);

        expect($this->executive->refresh()->phone_verified_at)->toBeNull();
    });
});

describe('push subscriptions', function () {
    it('registers a device, stores its keys encrypted, and turns push on', function () {
        $this->actingAs($this->executive)->post(route('push-subscriptions.store'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['p256dh' => 'public-key-value', 'auth' => 'auth-secret'],
            'content_encoding' => 'aes128gcm',
        ])->assertSessionHasNoErrors();

        $subscription = PushSubscription::sole();

        expect($subscription->user_id)->toBe($this->executive->id)
            ->and($subscription->auth_token)->toBe('auth-secret')
            ->and($subscription->getRawOriginal('auth_token'))->not->toBe('auth-secret')
            ->and($this->executive->refresh()->preferences()->push_enabled)->toBeTrue();
    });

    it('rejects a non-https endpoint', function () {
        $this->actingAs($this->executive)->post(route('push-subscriptions.store'), [
            'endpoint' => 'http://insecure.example/push',
            'keys' => ['p256dh' => 'a', 'auth' => 'b'],
        ])->assertSessionHasErrors('endpoint');
    });

    it('turns push off when the last device is removed', function () {
        $subscription = PushSubscription::factory()->for($this->executive)->create();
        $this->executive->preferences()->update(['push_enabled' => true]);

        $this->actingAs($this->executive)->delete(route('push-subscriptions.destroy', $subscription))->assertRedirect();

        expect(PushSubscription::count())->toBe(0)
            ->and($this->executive->refresh()->preferences()->push_enabled)->toBeFalse();
    });

    it('does not let a user remove another user\'s device', function () {
        $subscription = PushSubscription::factory()->create();

        $this->actingAs($this->executive)->delete(route('push-subscriptions.destroy', $subscription))->assertNotFound();
    });
});

describe('delivery history', function () {
    it('shows users only their own deliveries', function () {
        NotificationDelivery::factory()->for($this->executive)->create();
        NotificationDelivery::factory()->create();

        $this->actingAs($this->executive)
            ->get(route('notifications.history'))
            ->assertInertia(fn ($page) => $page->component('notifications/history')->where('scope', 'mine')->has('deliveries.data', 1));
    });

    it('shows assistants every delivery', function () {
        NotificationDelivery::factory()->for($this->executive)->create();
        NotificationDelivery::factory()->create();

        $this->actingAs(User::factory()->assistant()->create())
            ->get(route('notifications.history'))
            ->assertInertia(fn ($page) => $page->where('scope', 'all')->has('deliveries.data', 2));
    });

    it('forbids executives from retrying failed reminders', function () {
        $reminder = Reminder::factory()->create(['status' => ReminderStatus::Failed]);

        $this->actingAs($this->executive)->post(route('reminders.retry', $reminder))->assertForbidden();
    });
});

describe('administration', function () {
    it('lets administrators change the default reminder intervals', function () {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'event_reminder_minutes' => [30, 10],
            'task_reminder_minutes' => [60],
            'presentation_reminder_minutes' => [1440],
            'default_timezone' => 'Asia/Manila',
        ])->assertSessionHasNoErrors();

        $newUser = User::factory()->create();

        expect($newUser->preferences()->event_reminder_minutes)->toBe([30, 10]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated', 'user_id' => $admin->id]);
    });

    it('forbids assistants from application settings and the audit log', function () {
        $assistant = User::factory()->assistant()->create();

        $this->actingAs($assistant)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($assistant)->get(route('admin.audit-logs.index'))->assertForbidden();
    });

    it('shows the audit log to administrators', function () {
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.audit-logs.index'))
            ->assertInertia(fn ($page) => $page->component('admin/audit-logs'));
    });
});
