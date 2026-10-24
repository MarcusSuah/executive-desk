<?php

namespace App\Http\Controllers;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Services\AuditLogger;
use App\Services\Reminders\ReminderDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ReminderController extends Controller
{
    /**
     * Switch an individual upcoming reminder off or back on.
     */
    public function toggle(Request $request, Reminder $reminder, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('toggle', $reminder);

        $enable = $reminder->status === ReminderStatus::Disabled;

        Reminder::query()
            ->whereKey($reminder->id)
            ->where('status', $reminder->status->value)
            ->update([
                'status' => $enable ? ReminderStatus::Pending->value : ReminderStatus::Disabled->value,
                'status_reason' => $enable ? null : 'Turned off by '.$request->user()->name,
                'updated_at' => now(),
            ]);

        $audit->log($enable ? 'reminder.enabled' : 'reminder.disabled', $reminder->remindable, [], [
            'reminder_id' => $reminder->id,
            'minutes_before' => $reminder->minutes_before,
            'channel' => $reminder->channel->value,
        ], $request->user());

        return back();
    }

    /**
     * Retry a failed reminder.
     */
    public function retry(Request $request, Reminder $reminder, ReminderDispatcher $dispatcher, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('retry', $reminder);

        $dispatcher->retry($reminder);

        $audit->log('reminder.retried', $reminder->remindable, [], ['reminder_id' => $reminder->id], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder queued for another attempt.')]);

        return back();
    }
}
