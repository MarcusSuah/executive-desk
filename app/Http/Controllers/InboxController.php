<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Actions for the in-app notification bell.
 */
class InboxController extends Controller
{
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
