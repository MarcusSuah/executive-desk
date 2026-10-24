import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function StatCard({
    label,
    value,
    icon: Icon,
    tone = 'neutral',
    href,
    hint,
}: {
    label: string;
    value: number | string;
    icon: LucideIcon;
    tone?: 'neutral' | 'info' | 'success' | 'warning' | 'danger';
    href?: NonNullable<InertiaLinkProps['href']>;
    hint?: string;
}) {
    const content = (
        <>
            <span
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-lg',
                    {
                        'bg-muted text-foreground': tone === 'neutral',
                        'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300':
                            tone === 'info',
                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300':
                            tone === 'success',
                        'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300':
                            tone === 'warning',
                        'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300':
                            tone === 'danger',
                    },
                )}
            >
                <Icon className="size-5" aria-hidden />
            </span>
            <span className="min-w-0">
                <span className="block text-2xl leading-tight font-semibold tabular-nums">
                    {value}
                </span>
                <span className="block truncate text-sm text-muted-foreground">
                    {label}
                </span>
                {hint && (
                    <span className="block truncate text-xs text-muted-foreground">
                        {hint}
                    </span>
                )}
            </span>
        </>
    );

    const className =
        'flex items-center gap-3 rounded-xl border bg-card p-4 shadow-xs transition-colors';

    return href ? (
        <Link href={href} className={cn(className, 'hover:bg-accent/50')}>
            {content}
        </Link>
    ) : (
        <div className={className}>{content}</div>
    );
}
