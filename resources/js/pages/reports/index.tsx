import { Head, router } from '@inertiajs/react';
import { BarChart3, FileDown } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useAuth } from '@/hooks/use-auth';
import { exportMethod, index } from '@/routes/reports';
import type { Option, UserOption } from '@/types';

type Filters = { report: string; from: string; to: string; executive: string };

type ReportData = {
    title: string;
    columns: Record<string, string>;
    rows: Record<string, string>[];
    summary: Record<string, number | string>;
    chart: { label: string; value: number }[];
};

export default function ReportsIndex({
    report,
    filters,
    reports,
    executives,
}: {
    report: ReportData;
    filters: Filters;
    reports: Option[];
    executives: UserOption[];
}) {
    const { timezone } = useAuth();
    const columns = Object.entries(report.columns);
    const maxValue = Math.max(1, ...report.chart.map((point) => point.value));
    const query = Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== ''),
    );

    const visit = (overrides: Partial<Filters>) =>
        router.get(
            index.url({
                query: Object.fromEntries(
                    Object.entries({ ...filters, ...overrides }).filter(
                        ([, value]) => value !== '',
                    ),
                ),
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title="Reports" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Reports"
                    description={`Computed from live records using your access. Dates are in ${timezone}.`}
                    actions={
                        <>
                            {(['csv', 'xlsx', 'pdf'] as const).map((format) => (
                                <Button
                                    key={format}
                                    variant="outline"
                                    size="sm"
                                    asChild
                                >
                                    <a
                                        href={exportMethod.url({
                                            query: { ...query, format },
                                        })}
                                    >
                                        <FileDown />
                                        {format === 'xlsx'
                                            ? 'Excel'
                                            : format.toUpperCase()}
                                    </a>
                                </Button>
                            ))}
                        </>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <NativeSelect
                        className="w-auto"
                        value={filters.report}
                        onChange={(e) => visit({ report: e.target.value })}
                        aria-label="Report"
                    >
                        {reports.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </NativeSelect>
                    <Input
                        type="date"
                        className="w-auto"
                        value={filters.from}
                        onChange={(e) =>
                            e.target.value && visit({ from: e.target.value })
                        }
                        aria-label="From date"
                    />
                    <span className="text-sm text-muted-foreground">to</span>
                    <Input
                        type="date"
                        className="w-auto"
                        value={filters.to}
                        onChange={(e) =>
                            e.target.value && visit({ to: e.target.value })
                        }
                        aria-label="To date"
                    />
                    {executives.length > 0 && (
                        <NativeSelect
                            className="w-auto"
                            value={filters.executive}
                            onChange={(e) =>
                                visit({ executive: e.target.value })
                            }
                            aria-label="Executive"
                        >
                            <option value="">All executives</option>
                            {executives.map((executive) => (
                                <option key={executive.id} value={executive.id}>
                                    {executive.name}
                                </option>
                            ))}
                        </NativeSelect>
                    )}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {Object.entries(report.summary).map(([label, value]) => (
                        <div
                            key={label}
                            className="rounded-xl border bg-card p-4"
                        >
                            <p className="text-2xl font-semibold tabular-nums">
                                {value}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {label}
                            </p>
                        </div>
                    ))}
                </div>

                {report.chart.length > 0 && (
                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {report.title}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-1.5">
                                {report.chart.map((point) => (
                                    <li
                                        key={point.label}
                                        className="flex items-center gap-3 text-sm"
                                    >
                                        <span className="w-40 shrink-0 truncate text-muted-foreground">
                                            {point.label}
                                        </span>
                                        <span className="h-3 flex-1 overflow-hidden rounded-full bg-muted">
                                            <span
                                                className="block h-full rounded-full bg-sky-600"
                                                style={{
                                                    width: `${(point.value / maxValue) * 100}%`,
                                                }}
                                            />
                                        </span>
                                        <span className="w-10 text-right tabular-nums">
                                            {point.value}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                {report.rows.length === 0 ? (
                    <EmptyState
                        icon={BarChart3}
                        title="No records in this period"
                        description="Try a wider date range."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    {columns.map(([key, label]) => (
                                        <th
                                            key={key}
                                            className="px-3 py-2 font-medium"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {report.rows
                                    .slice(0, 500)
                                    .map((row, position) => (
                                        <tr key={position}>
                                            {columns.map(([key]) => (
                                                <td
                                                    key={key}
                                                    className="px-3 py-2"
                                                >
                                                    {row[key]}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                            </tbody>
                        </table>
                        {report.rows.length > 500 && (
                            <p className="border-t px-3 py-2 text-xs text-muted-foreground">
                                Showing the first 500 of {report.rows.length}{' '}
                                rows. Export to see all.
                            </p>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: index() }],
};
