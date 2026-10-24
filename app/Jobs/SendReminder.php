<?php

namespace App\Jobs;

use App\Services\Reminders\ReminderDeliverer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendReminder implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $reminderId) {}

    /**
     * The number of times the job may be attempted.
     */
    public function tries(): int
    {
        return config('executive-desk.reminders.max_attempts');
    }

    /**
     * The exponential backoff, in seconds, between attempts.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return config('executive-desk.reminders.backoff_seconds');
    }

    /**
     * Execute the job.
     */
    public function handle(ReminderDeliverer $deliverer): void
    {
        $deliverer->deliver($this->reminderId, $this->attempts() >= $this->tries());
    }

    /**
     * Handle a job failure after all attempts are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        app(ReminderDeliverer::class)->markFailed(
            $this->reminderId,
            'Retry limit reached'.($exception !== null ? ': '.$exception->getMessage() : '.'),
        );
    }
}
