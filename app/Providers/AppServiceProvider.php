<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\CalendarEvent;
use App\Models\ChecklistItem;
use App\Models\EventAttendee;
use App\Models\ExecutiveBriefing;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\Presentation;
use App\Models\PushSubscription;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\Push\PushGateway;
use App\Services\Notifications\Push\WebPushGateway;
use App\Services\Notifications\Sms\LogSmsGateway;
use App\Services\Notifications\Sms\NullSmsGateway;
use App\Services\Notifications\Sms\SmsGateway;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, fn (): SmsGateway => match (config('services.sms.driver')) {
            'log' => new LogSmsGateway,
            default => new NullSmsGateway,
        });

        $this->app->singleton(PushGateway::class, WebPushGateway::class);

        // One audit logger per request, so it can tell whether the request was already audited.
        $this->app->scoped(AuditLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureRateLimiting();

        if ($this->app->runningInConsole()) {
            // `composer run dev` also runs the scheduler, which drives reminders and briefings.
            DevCommands::artisan('schedule:work', 'scheduler');
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Store short, stable type names instead of class names in polymorphic columns.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'calendar_event' => CalendarEvent::class,
            'event_attendee' => EventAttendee::class,
            'task' => Task::class,
            'presentation' => Presentation::class,
            'checklist_item' => ChecklistItem::class,
            'attachment' => Attachment::class,
            'reminder' => Reminder::class,
            'notification_preference' => NotificationPreference::class,
            'notification_delivery' => NotificationDelivery::class,
            'push_subscription' => PushSubscription::class,
            'executive_briefing' => ExecutiveBriefing::class,
            'audit_log' => AuditLog::class,
            'app_setting' => AppSetting::class,
        ]);
    }

    /**
     * Configure application rate limiters.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('phone-verification', fn (Request $request) => Limit::perHour(5)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('offline-sync', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
    }
}
