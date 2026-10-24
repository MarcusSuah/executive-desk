<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * An item in the in-app notification bell.
 */
class InAppNotice extends Notification
{
    /**
     * Create a new notification instance.
     *
     * @param  string  $kind  e.g. "event_reminder", "schedule_change", "briefing", "task_assigned"
     * @param  string|null  $url  Relative path within the application to open when clicked.
     * @param  string|null  $category  The alert category (see NotificationCategories).
     */
    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public ?string $url = null,
        public ?string $category = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, string|null>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'category' => $this->category,
        ];
    }
}
