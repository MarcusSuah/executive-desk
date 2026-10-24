<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\ReminderStatus;
use App\Models\NotificationDelivery;
use App\Models\Reminder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Operational indicators for the notification pipeline.
 */
class SystemHealth
{
    public const SCHEDULER_HEARTBEAT_KEY = 'scheduler:last-run';

    /**
     * Collect delivery and queue health indicators.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $since = now()->subDay();

        $deliveries = NotificationDelivery::query()
            ->where('attempted_at', '>=', $since)
            ->selectRaw('channel, status, COUNT(*) as aggregate')
            ->groupBy('channel', 'status')
            ->get()
            ->groupBy(fn ($row) => $row->channel->value)
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [$row->status->value => (int) $row->getAttribute('aggregate')]))
            ->all();

        $lastSchedulerRun = Cache::get(self::SCHEDULER_HEARTBEAT_KEY);

        return [
            'deliveries_24h' => $deliveries,
            'failed_deliveries_24h' => NotificationDelivery::query()
                ->where('attempted_at', '>=', $since)
                ->where('status', DeliveryStatus::Failed->value)
                ->count(),
            'reminders' => [
                'pending' => Reminder::query()->where('status', ReminderStatus::Pending->value)->count(),
                'in_flight' => Reminder::query()->whereIn('status', [ReminderStatus::Queued->value, ReminderStatus::Processing->value])->count(),
                'overdue_pending' => Reminder::query()
                    ->where('status', ReminderStatus::Pending->value)
                    ->where('scheduled_for', '<', now()->subMinutes(5))
                    ->count(),
                'failed_24h' => Reminder::query()
                    ->where('status', ReminderStatus::Failed->value)
                    ->where('updated_at', '>=', $since)
                    ->count(),
            ],
            'queue' => [
                'pending_jobs' => DB::table(config('queue.connections.database.table', 'jobs'))->count(),
                'failed_jobs' => DB::table(config('queue.failed.table', 'failed_jobs'))->count(),
            ],
            'scheduler_last_run' => is_string($lastSchedulerRun) ? $lastSchedulerRun : null,
            'scheduler_healthy' => is_string($lastSchedulerRun) && now()->diffInMinutes($lastSchedulerRun, true) <= 5,
        ];
    }
}
