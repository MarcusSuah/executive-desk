<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'roles' => $user?->getRoleNames()->values()->all() ?? [],
                'permissions' => $user !== null
                    ? array_values(array_filter(
                        array_map(fn (Permission $permission) => $permission->value, Permission::cases()),
                        fn (string $permission) => $user->can($permission),
                    ))
                    : [],
            ],
            'inbox' => fn (): ?array => $user === null ? null : [
                'unread' => $user->unreadNotifications()->count(),
                'items' => $user->notifications()
                    ->latest()
                    ->limit(8)
                    ->get()
                    ->map(fn (DatabaseNotification $notification) => [
                        'id' => $notification->id,
                        'kind' => $notification->data['kind'] ?? 'notice',
                        'title' => $notification->data['title'] ?? '',
                        'body' => $notification->data['body'] ?? '',
                        'read' => $notification->read_at !== null,
                        'created_at' => $notification->created_at?->toIso8601ZuluString(),
                    ])
                    ->all(),
            ],
            'pwa' => [
                'vapidPublicKey' => config('services.webpush.public_key'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
