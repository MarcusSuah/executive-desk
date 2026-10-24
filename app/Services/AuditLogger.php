<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Notifications\ActivityNotifier;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class AuditLogger
{
    /**
     * Attribute names that must never be written to the audit trail.
     *
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'auth_token', 'public_key', 'endpoint', 'endpoint_hash', 'token', 'secret', 'api_key',
        'current_password', 'password_confirmation', 'code', 'recovery_code', 'client_secret',
        'google_client_secret', 'google_id',
    ];

    /**
     * Whether an entry has been written during the current request.
     */
    private bool $recordedThisRequest = false;

    public function __construct(private ActivityNotifier $activity) {}

    /**
     * Attribute names that are noise in a change history.
     *
     * @var array<int, string>
     */
    private const IGNORED_KEYS = ['created_at', 'updated_at', 'version'];

    /**
     * Record an audit entry.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?User $actor = null,
    ): AuditLog {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        $current = Auth::user();
        $actor ??= $current instanceof User ? $current : null;

        $log = new AuditLog;
        $log->user_id = $actor?->getKey();
        $log->action = $action;
        $log->old_values = $this->sanitize($oldValues) ?: null;
        $log->new_values = $this->sanitize($newValues) ?: null;
        $log->ip_address = $request?->ip();
        $log->user_agent = $request?->userAgent() !== null ? Str::limit($request->userAgent(), 490) : null;

        if ($auditable !== null) {
            $log->auditable()->associate($auditable);
        }

        $log->save();

        $this->recordedThisRequest = true;

        try {
            $this->activity->notify($log, $auditable, $actor);
        } catch (Throwable $exception) {
            // A notification problem must never prevent an action from being audited.
            report($exception);
        }

        return $log;
    }

    /**
     * Mark the start of a new request.
     */
    public function startRequest(): void
    {
        $this->recordedThisRequest = false;
    }

    /**
     * Determine whether an audit entry was written during the current request.
     */
    public function recordedThisRequest(): bool
    {
        return $this->recordedThisRequest;
    }

    /**
     * Record the changes of a model that has just been updated.
     */
    public function logChanges(string $action, Model $model, ?User $actor = null): ?AuditLog
    {
        $changes = Arr::except($model->getChanges(), self::IGNORED_KEYS);

        if ($changes === []) {
            return null;
        }

        $original = Arr::only($model->getPrevious(), array_keys($changes));

        return $this->log($action, $model, $original, $changes, $actor);
    }

    /**
     * Remove secrets and normalize values for JSON storage.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if (in_array(Str::lower((string) $key), self::SENSITIVE_KEYS, true)) {
                continue;
            }

            $clean[$key] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
                is_array($value) => $this->sanitize($value),
                is_string($value) => Str::limit($value, 2000),
                default => $value,
            };
        }

        return $clean;
    }
}
