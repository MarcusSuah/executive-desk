<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\Permission;
use App\Http\Resources\NotificationDeliveryResource;
use App\Models\NotificationDelivery;
use App\Support\FormOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationHistoryController extends Controller
{
    /**
     * The notification types recorded in the delivery history.
     *
     * @var array<int, string>
     */
    public const TYPES = [
        'event_reminder', 'task_reminder', 'presentation_reminder', 'schedule_change',
        'daily_briefing', 'weekly_briefing', 'test',
    ];

    /**
     * List delivery attempts: the user's own, or everyone's for users allowed to see all.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'channel' => ['nullable', Rule::enum(NotificationChannel::class)],
            'status' => ['nullable', Rule::enum(DeliveryStatus::class)],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'recipient' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $user = $request->user();
        $canViewAll = $user->can(Permission::ViewAllDeliveries->value);
        $timezone = $user->timezone;

        $deliveries = NotificationDelivery::query()
            ->with(['user:id,name', 'deliverable'])
            ->when(! $canViewAll, fn (Builder $query) => $query->where('user_id', $user->id))
            ->when($canViewAll && isset($filters['recipient']), fn (Builder $query) => $query->where('user_id', $filters['recipient']))
            ->when($filters['channel'] ?? null, fn (Builder $query, $channel) => $query->where('channel', $channel))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, $type) => $query->where('notification_type', $type))
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->where('attempted_at', '>=', CarbonImmutable::parse($from, $timezone)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->where('attempted_at', '<', CarbonImmutable::parse($to, $timezone)->addDay()->startOfDay()->utc()))
            ->latest('attempted_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('notifications/history', [
            'deliveries' => NotificationDeliveryResource::collection($deliveries),
            'filters' => [
                'channel' => $filters['channel'] ?? '',
                'status' => $filters['status'] ?? '',
                'type' => $filters['type'] ?? '',
                'recipient' => isset($filters['recipient']) ? (string) $filters['recipient'] : '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'options' => [
                'channels' => NotificationChannel::options(),
                'statuses' => DeliveryStatus::options(),
                'types' => array_map(fn (string $type) => ['value' => $type, 'label' => ucwords(str_replace('_', ' ', $type))], self::TYPES),
                'users' => $canViewAll ? FormOptions::users() : [],
            ],
            'scope' => $canViewAll ? 'all' : 'mine',
        ]);
    }
}
