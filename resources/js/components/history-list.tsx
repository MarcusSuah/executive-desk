import { History } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAuth } from '@/hooks/use-auth';
import { formatDateTime } from '@/lib/datetime';
import { auditLabel, humanize } from '@/lib/labels';
import type { AuditEntry } from '@/types';

/**
 * Summarize the changed fields of an audit entry, e.g. "Title, Starts At".
 */
function changedFields(entry: AuditEntry): string {
    const keys = Object.keys(entry.new_values ?? {}).filter(
        (key) => !['attachment_id', 'reminder_id'].includes(key),
    );

    if (
        entry.action.startsWith('checklist.') ||
        entry.action.startsWith('attachment.')
    ) {
        const value = entry.new_values?.title ?? entry.new_values?.file;

        return typeof value === 'string' ? value : '';
    }

    return keys.slice(0, 4).map(humanize).join(', ');
}

export default function HistoryList({ entries }: { entries: AuditEntry[] }) {
    const { timezone } = useAuth();

    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <History className="size-4" aria-hidden />
                    History
                </CardTitle>
            </CardHeader>
            <CardContent>
                {entries.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No activity recorded yet.
                    </p>
                ) : (
                    <ol className="relative space-y-4 border-l pl-4">
                        {entries.map((entry) => {
                            const fields = changedFields(entry);

                            return (
                                <li key={entry.id} className="relative">
                                    <span
                                        className="absolute top-1.5 -left-[21px] size-2.5 rounded-full border-2 border-background bg-muted-foreground"
                                        aria-hidden
                                    />
                                    <p className="text-sm font-medium">
                                        {auditLabel(entry.action)}
                                    </p>
                                    {fields && (
                                        <p className="text-sm text-muted-foreground">
                                            {fields}
                                        </p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        {entry.actor ?? 'System'} ·{' '}
                                        {formatDateTime(
                                            entry.created_at,
                                            timezone,
                                        )}
                                    </p>
                                </li>
                            );
                        })}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}
