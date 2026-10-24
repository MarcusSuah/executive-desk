<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('audit:prune {--days= : Delete entries older than this many days (defaults to AUDIT_RETENTION_DAYS)}')]
#[Description('Delete audit log entries older than the retention period (does nothing when retention is 0)')]
class PruneAuditLogs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('executive-desk.audit.retention_days'));

        if ($days <= 0) {
            $this->components->info('Audit retention is unlimited; nothing was deleted.');

            return self::SUCCESS;
        }

        $deleted = 0;

        do {
            $batch = AuditLog::query()->where('created_at', '<', now()->subDays($days))->limit(5000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->components->info("Deleted {$deleted} audit entr".($deleted === 1 ? 'y' : 'ies')." older than {$days} days.");

        return self::SUCCESS;
    }
}
