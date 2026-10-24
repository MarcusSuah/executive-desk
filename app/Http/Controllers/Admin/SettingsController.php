<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationChannel;
use App\Http\Controllers\Controller;
use App\Mail\NoticeMail;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationSender;
use App\Services\Notifications\OutboundMessage;
use App\Services\Notifications\Sms\SmsGateway;
use App\Services\SystemHealth;
use App\Support\AppSettings;
use App\Support\FormOptions;
use App\Support\Timezones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Show application settings, integration status, and delivery health.
     */
    public function edit(NotificationSender $sender, SmsGateway $sms, SystemHealth $health): Response
    {
        return Inertia::render('admin/settings', [
            'settings' => [
                'event_reminder_minutes' => AppSettings::defaultEventReminderMinutes(),
                'task_reminder_minutes' => AppSettings::defaultTaskReminderMinutes(),
                'presentation_reminder_minutes' => AppSettings::defaultPresentationReminderMinutes(),
                'default_timezone' => AppSettings::defaultTimezone(),
            ],
            'integrations' => [
                'mail' => [
                    'configured' => $sender->channel(NotificationChannel::Mail)->isConfigured(),
                    'detail' => 'Mailer: '.config('mail.default').(in_array(config('mail.default'), ['log', 'array'], true) ? ' (development only — no real email is sent)' : ''),
                    'from' => config('mail.from.address'),
                ],
                'sms' => [
                    'configured' => $sms->isConfigured(),
                    'detail' => 'Gateway: '.$sms->name(),
                ],
                'push' => [
                    'configured' => $sender->channel(NotificationChannel::Push)->isConfigured(),
                    'detail' => $sender->channel(NotificationChannel::Push)->isConfigured() ? 'VAPID keys configured' : 'Run php artisan push:vapid and add the keys to .env',
                ],
                'queue' => ['configured' => true, 'detail' => 'Connection: '.config('queue.default')],
            ],
            'health' => $health->snapshot(),
            'timezones' => FormOptions::timezones(),
            'reminderMinutes' => FormOptions::reminderMinutes(),
        ]);
    }

    /**
     * Save the global notification defaults.
     */
    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $minutes = ['integer', 'distinct', Rule::in(AppSettings::SUPPORTED_REMINDER_MINUTES)];

        $validated = $request->validate([
            'event_reminder_minutes' => ['present', 'array', 'max:10'],
            'event_reminder_minutes.*' => $minutes,
            'task_reminder_minutes' => ['present', 'array', 'max:10'],
            'task_reminder_minutes.*' => $minutes,
            'presentation_reminder_minutes' => ['present', 'array', 'max:10'],
            'presentation_reminder_minutes.*' => $minutes,
            'default_timezone' => ['required', Rule::in(Timezones::identifiers())],
        ]);

        $sorted = fn (array $values): array => collect($values)->map(fn ($value) => (int) $value)->sortDesc()->values()->all();

        $previous = [
            'event_reminder_minutes' => AppSettings::defaultEventReminderMinutes(),
            'task_reminder_minutes' => AppSettings::defaultTaskReminderMinutes(),
            'presentation_reminder_minutes' => AppSettings::defaultPresentationReminderMinutes(),
            'default_timezone' => AppSettings::defaultTimezone(),
        ];

        AppSettings::set(AppSettings::EVENT_REMINDER_MINUTES, $sorted($validated['event_reminder_minutes']));
        AppSettings::set(AppSettings::TASK_REMINDER_MINUTES, $sorted($validated['task_reminder_minutes']));
        AppSettings::set(AppSettings::PRESENTATION_REMINDER_MINUTES, $sorted($validated['presentation_reminder_minutes']));
        AppSettings::set(AppSettings::DEFAULT_TIMEZONE, $validated['default_timezone']);

        $audit->log('settings.updated', null, $previous, $validated, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved. New defaults apply to users who have not customized their reminders.')]);

        return back();
    }

    /**
     * Send a test email to the signed-in administrator.
     */
    public function testMail(Request $request, NotificationSender $sender): RedirectResponse
    {
        $user = $request->user();

        $result = $sender->send($user, NotificationChannel::Mail, new OutboundMessage(
            type: 'test',
            title: 'Test email',
            body: 'Test email',
            mailable: new NoticeMail(
                subjectLine: 'Executive Desk test email',
                heading: 'Email delivery is working',
                intro: 'This test message was sent from the application settings page.',
                details: ['Mailer' => (string) config('mail.default'), 'Sent at' => now()->setTimezone($user->timezone)->format('D, M j, Y g:i A T')],
            ),
            smsText: '',
        ));

        Inertia::flash('toast', [
            'type' => $result->wasSent() ? 'success' : 'error',
            'message' => $result->wasSent()
                ? __('Test email accepted by the mail transport for :email.', ['email' => $user->email])
                : __('Test email failed: :reason', ['reason' => (string) $result->error]),
        ]);

        return back();
    }
}
