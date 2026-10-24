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
use App\Support\SignInSettings;
use App\Support\Timezones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'signIn' => [
                'google_client_id' => (string) AppSettings::get(SignInSettings::GOOGLE_CLIENT_ID, ''),
                'google_hosted_domain' => (string) AppSettings::get(SignInSettings::GOOGLE_HOSTED_DOMAIN, ''),
                'google_secret_saved' => SignInSettings::hasStoredGoogleSecret(),
                'google_enabled' => SignInSettings::googleEnabled(),
                'google_from_environment' => filled(config('services.google.client_id')),
                'google_redirect_uri' => SignInSettings::googleRedirectUri(),
                'background_url' => SignInSettings::backgroundUrl(),
                'background_uploaded' => SignInSettings::backgroundPath() !== null,
            ],
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

    /**
     * Save the Google sign-in credentials. A blank secret keeps the saved one.
     */
    public function updateSignIn(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'google_client_id' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9\-_.]+$/'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],
            'google_hosted_domain' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9.\-]+\.[a-z]{2,}$/i'],
            'clear' => ['boolean'],
        ], [
            'google_client_id.regex' => 'The client ID looks invalid. Copy it from the Google Cloud console.',
            'google_hosted_domain.regex' => 'Enter a domain such as yourcompany.com.',
        ]);

        if ($request->boolean('clear')) {
            SignInSettings::clearGoogle();
            $audit->log('settings.google_sign_in_cleared', null, [], [], $request->user());
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Google sign-in settings removed.')]);

            return back();
        }

        if (filled($validated['google_client_id'] ?? null)
            && ! filled($validated['google_client_secret'] ?? null)
            && ! SignInSettings::hasStoredGoogleSecret()) {
            throw ValidationException::withMessages(['google_client_secret' => 'Enter the client secret.']);
        }

        SignInSettings::saveGoogle(
            $validated['google_client_id'] ?? null,
            $validated['google_client_secret'] ?? null,
            $validated['google_hosted_domain'] ?? null,
        );

        // The secret itself is never written to the audit log.
        $audit->log('settings.google_sign_in_updated', null, [], [
            'google_client_id' => $validated['google_client_id'] ?? null,
            'google_hosted_domain' => $validated['google_hosted_domain'] ?? null,
            'secret_changed' => filled($validated['google_client_secret'] ?? null),
        ], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => SignInSettings::googleEnabled()
            ? __('Google sign-in is on. The button now appears on the sign-in page.')
            : __('Google sign-in settings saved.')]);

        return back();
    }

    /**
     * Upload a new sign-in background image.
     */
    public function uploadBackground(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'background' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=800,min_height=600'],
        ], [
            'background.dimensions' => 'Use an image at least 800 × 600 pixels; 1600 pixels wide or more looks best.',
        ]);

        $previous = SignInSettings::backgroundPath();
        $path = $validated['background']->store(SignInSettings::BACKGROUND_DIRECTORY, 'local');

        AppSettings::set(SignInSettings::BACKGROUND_PATH, $path);

        if ($previous !== null) {
            Storage::disk('local')->delete($previous);
        }

        $audit->log('settings.sign_in_background_updated', null, [], [], $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sign-in background updated.')]);

        return back();
    }

    /**
     * Remove the uploaded sign-in background and use the default.
     */
    public function removeBackground(Request $request, AuditLogger $audit): RedirectResponse
    {
        $previous = SignInSettings::backgroundPath();

        AppSettings::set(SignInSettings::BACKGROUND_PATH, '');

        if ($previous !== null) {
            Storage::disk('local')->delete($previous);
        }

        $audit->log('settings.sign_in_background_removed', null, [], [], $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sign-in background reset to the default.')]);

        return back();
    }
}
