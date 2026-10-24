<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\BriefingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ChecklistItemController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\NotificationHistoryController;
use App\Http\Controllers\OfflineSyncController;
use App\Http\Controllers\PresentationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['guest', 'throttle:10,1'])->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');

    Route::resource('events', EventController::class)->except(['index']);
    Route::post('events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
    Route::post('events/{event}/complete', [EventController::class, 'complete'])->name('events.complete');

    Route::resource('tasks', TaskController::class);
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');

    Route::resource('presentations', PresentationController::class);
    Route::post('presentations/{presentation}/approve', [PresentationController::class, 'approve'])->name('presentations.approve');

    Route::post('events/{event}/checklist', [ChecklistItemController::class, 'storeForEvent'])->name('events.checklist.store');
    Route::post('tasks/{task}/checklist', [ChecklistItemController::class, 'storeForTask'])->name('tasks.checklist.store');
    Route::post('presentations/{presentation}/checklist', [ChecklistItemController::class, 'storeForPresentation'])->name('presentations.checklist.store');
    Route::patch('checklist-items/{item}', [ChecklistItemController::class, 'update'])->name('checklist-items.update');
    Route::delete('checklist-items/{item}', [ChecklistItemController::class, 'destroy'])->name('checklist-items.destroy');

    Route::middleware('throttle:uploads')->group(function () {
        Route::post('events/{event}/attachments', [AttachmentController::class, 'storeForEvent'])->name('events.attachments.store');
        Route::post('tasks/{task}/attachments', [AttachmentController::class, 'storeForTask'])->name('tasks.attachments.store');
        Route::post('presentations/{presentation}/attachments', [AttachmentController::class, 'storeForPresentation'])->name('presentations.attachments.store');
    });
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('offline/snapshot', [OfflineSyncController::class, 'snapshot'])->name('offline.snapshot');
    Route::post('offline/sync', [OfflineSyncController::class, 'sync'])->middleware('throttle:offline-sync')->name('offline.sync');

    Route::patch('reminders/{reminder}/toggle', [ReminderController::class, 'toggle'])->name('reminders.toggle');
    Route::post('reminders/{reminder}/retry', [ReminderController::class, 'retry'])->name('reminders.retry');

    Route::get('briefings', [BriefingController::class, 'index'])->name('briefings.index');
    Route::post('briefings/generate', [BriefingController::class, 'generate'])->middleware('throttle:20,1')->name('briefings.generate');
    Route::get('briefings/{briefing}', [BriefingController::class, 'show'])->name('briefings.show');
    Route::post('briefings/{briefing}/send', [BriefingController::class, 'send'])->middleware('throttle:10,1')->name('briefings.send');
    Route::get('briefings/{briefing}/pdf', [BriefingController::class, 'pdf'])->middleware('throttle:exports')->name('briefings.pdf');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->middleware('throttle:exports')->name('reports.export');

    Route::get('notifications', [NotificationHistoryController::class, 'index'])->name('notifications.history');

    Route::post('inbox/read-all', [InboxController::class, 'readAll'])->name('inbox.read-all');
    Route::post('inbox/{notification}/read', [InboxController::class, 'read'])->whereUuid('notification')->name('inbox.read');

    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::post('push-subscriptions/unsubscribe', [PushSubscriptionController::class, 'unsubscribe'])->name('push-subscriptions.unsubscribe');
    Route::post('push-subscriptions/test', [PushSubscriptionController::class, 'test'])->middleware('throttle:5,1')->name('push-subscriptions.test');
    Route::delete('push-subscriptions/{subscription}', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:'.Permission::ManageUsers->value)->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
            Route::patch('users/{user}/active', [UserController::class, 'toggleActive'])->name('users.active');
            Route::post('users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])->name('users.password-reset');
        });

        Route::middleware('can:'.Permission::ManageSettings->value)->group(function () {
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::post('settings/test-mail', [SettingsController::class, 'testMail'])->middleware('throttle:5,1')->name('settings.test-mail');
        });

        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->middleware('can:'.Permission::ViewAuditLogs->value)
            ->name('audit-logs.index');
    });
});

require __DIR__.'/settings.php';
