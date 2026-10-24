<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Actions for the in-app notification bell.
 */
class InboxController extends Controller
{
    /**
     * List all of the user's notifications.
     */
    public function index(Request $request): Response
    {
        $filter = $request->validate(['filter' => ['nullable', 'in:all,unread']])['filter'] ?? 'all';
        $user = $request->user();

        $notifications = ($filter === 'unread' ? $user->unreadNotifications() : $user->notifications())
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'kind' => $notification->data['kind'] ?? 'notice',
                'title' => $notification->data['title'] ?? '',
                'body' => $notification->data['body'] ?? '',
                'has_link' => filled($notification->data['url'] ?? null),
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601ZuluString(),
            ]);

        return Inertia::render('inbox/index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark a notification read and open what it refers to.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        $url = $item->data['url'] ?? null;

        // Only follow in-app paths; never redirect off-site.
        return is_string($url) && Str::startsWith($url, '/') && ! Str::startsWith($url, '//')
            ? redirect($url)
            : back();
    }

    /**
     * Mark every notification read.
     */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
