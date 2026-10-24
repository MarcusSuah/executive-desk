<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reminder Engine
    |--------------------------------------------------------------------------
    |
    | grace_minutes: a due reminder older than this is skipped instead of being
    | sent late (for example after the scheduler was offline).
    | max_attempts / backoff_seconds: retry policy for transient delivery
    | failures. Backoff grows exponentially between attempts.
    | stale_queued_minutes / stale_processing_minutes: thresholds after which
    | reminders stuck in an intermediate state are recovered.
    |
    */

    'reminders' => [
        'grace_minutes' => (int) env('REMINDER_GRACE_MINUTES', 30),
        'max_attempts' => 4,
        'backoff_seconds' => [60, 300, 900],
        'stale_queued_minutes' => 60,
        'stale_processing_minutes' => 15,
        'dispatch_batch_size' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Recurring Tasks
    |--------------------------------------------------------------------------
    |
    | Occurrences of recurring tasks are generated this many days ahead.
    |
    */

    'recurring_tasks' => [
        'horizon_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Files are stored on a private disk and only served through authorized
    | download routes. max_kilobytes must not exceed PHP's upload_max_filesize
    | and post_max_size settings.
    |
    */

    'uploads' => [
        'disk' => env('ATTACHMENTS_DISK', 'local'),
        'max_kilobytes' => (int) env('ATTACHMENTS_MAX_KB', 20480),
        'mimes' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'odt', 'odp', 'ods'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Phone Verification
    |--------------------------------------------------------------------------
    */

    'phone_verification' => [
        'code_ttl_minutes' => 10,
        'max_attempts' => 5,
    ],

];
