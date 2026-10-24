import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

/**
 * Page links for a paginated resource collection.
 */
export default function PaginationNav<T>({
    paginator,
}: {
    paginator: Paginated<T>;
}) {
    const { meta } = paginator;

    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <nav
            className="flex flex-col items-center justify-between gap-3 sm:flex-row"
            aria-label="Pagination"
        >
            <p className="text-sm text-muted-foreground">
                Showing {meta.from}–{meta.to} of {meta.total}
            </p>
            <div className="flex flex-wrap gap-1">
                {meta.links.map((link, index) => {
                    const label = link.label
                        .replace('&laquo;', '‹')
                        .replace('&raquo;', '›');

                    return link.url ? (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url}
                            preserveScroll
                            className={cn(
                                'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm hover:bg-accent',
                                {
                                    'border-primary bg-primary text-primary-foreground hover:bg-primary/90':
                                        link.active,
                                },
                            )}
                            aria-current={link.active ? 'page' : undefined}
                        >
                            {label}
                        </Link>
                    ) : (
                        <span
                            key={`${link.label}-${index}`}
                            className="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm text-muted-foreground"
                        >
                            {label}
                        </span>
                    );
                })}
            </div>
        </nav>
    );
}
