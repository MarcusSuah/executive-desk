import { humanize, priorityClasses, statusClasses } from '@/lib/labels';
import { cn } from '@/lib/utils';

export function StatusBadge({
    status,
    label,
    className,
}: {
    status: string;
    label?: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                statusClasses[status] ?? statusClasses.pending,
                className,
            )}
        >
            {label ?? humanize(status)}
        </span>
    );
}

export function PriorityBadge({
    priority,
    className,
}: {
    priority: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                priorityClasses[priority] ?? priorityClasses.normal,
                className,
            )}
        >
            <span
                aria-hidden
                className={cn('size-1.5 rounded-full', {
                    'bg-slate-400': priority === 'low',
                    'bg-sky-500': priority === 'normal',
                    'bg-amber-500': priority === 'high',
                    'bg-red-500': priority === 'urgent',
                })}
            />
            {humanize(priority)}
        </span>
    );
}
