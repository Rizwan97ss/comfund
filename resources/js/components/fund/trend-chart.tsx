import { useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Td, Table } from '@/components/fund/primitives';
import {
    formatMoney,
    formatMoneyCompact,
    formatMonth,
    formatMonthShort,
} from '@/lib/format';

type TrendPoint = {
    month: string;
    collections_paise: number;
    expenses_paise: number;
};

const SERIES = [
    {
        key: 'collections_paise',
        label: 'Collections',
        color: 'var(--color-chart-collections)',
    },
    {
        key: 'expenses_paise',
        label: 'Expenses',
        color: 'var(--color-chart-expenses)',
    },
] as const;

function TrendTooltip({
    active,
    payload,
    label,
}: {
    active?: boolean;
    payload?: readonly { dataKey?: unknown; value?: unknown }[];
    label?: unknown;
}) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="rounded-lg border bg-popover px-3 py-2 text-sm shadow-card">
            <p className="mb-1 font-semibold text-charcoal">
                {formatMonth(String(label))}
            </p>
            {SERIES.map((series) => (
                <p
                    key={series.key}
                    className="flex items-center justify-between gap-6 text-muted-foreground"
                >
                    <span className="flex items-center gap-1.5">
                        <span
                            className="size-2.5 rounded-sm"
                            style={{ background: series.color }}
                        />
                        {series.label}
                    </span>
                    <span className="font-medium text-charcoal tabular">
                        {formatMoney(
                            Number(
                                payload.find(
                                    (item) => item.dataKey === series.key,
                                )?.value ?? 0,
                            ),
                        )}
                    </span>
                </p>
            ))}
        </div>
    );
}

export function TrendChart({ data }: { data: TrendPoint[] }) {
    const [showTable, setShowTable] = useState(false);

    return (
        <div className="grid gap-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div
                    className="flex items-center gap-4 text-xs text-muted-foreground"
                    aria-hidden={showTable}
                >
                    {SERIES.map((series) => (
                        <span
                            key={series.key}
                            className="flex items-center gap-1.5"
                        >
                            <span
                                className="size-2.5 rounded-sm"
                                style={{ background: series.color }}
                            />
                            {series.label}
                        </span>
                    ))}
                </div>
                <button
                    type="button"
                    onClick={() => setShowTable((value) => !value)}
                    className="text-xs font-medium text-emerald-deep underline-offset-4 hover:underline"
                >
                    {showTable ? 'View as chart' : 'View as table'}
                </button>
            </div>

            {showTable ? (
                <div className="-mx-5 max-h-72 overflow-y-auto">
                    <Table
                        head={[
                            { label: 'Month' },
                            { label: 'Collections', className: 'text-right' },
                            { label: 'Expenses', className: 'text-right' },
                        ]}
                    >
                        {data.map((point) => (
                            <tr key={point.month}>
                                <Td>{formatMonth(point.month)}</Td>
                                <Td className="text-right tabular">
                                    {formatMoney(point.collections_paise)}
                                </Td>
                                <Td className="text-right tabular">
                                    {formatMoney(point.expenses_paise)}
                                </Td>
                            </tr>
                        ))}
                    </Table>
                </div>
            ) : (
                <div
                    className="h-72"
                    role="img"
                    aria-label="Monthly collections and expenses for the last twelve months. A table view is available."
                >
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={data}
                            barGap={2}
                            barCategoryGap="28%"
                            margin={{ top: 8, right: 4, left: 4, bottom: 0 }}
                        >
                            <CartesianGrid
                                vertical={false}
                                stroke="var(--border)"
                            />
                            <XAxis
                                dataKey="month"
                                tickFormatter={formatMonthShort}
                                tickLine={false}
                                axisLine={false}
                                tick={{
                                    fill: 'var(--muted-foreground)',
                                    fontSize: 12,
                                }}
                            />
                            <YAxis
                                tickFormatter={formatMoneyCompact}
                                tickLine={false}
                                axisLine={false}
                                width={64}
                                tick={{
                                    fill: 'var(--muted-foreground)',
                                    fontSize: 12,
                                }}
                            />
                            <Tooltip
                                cursor={{ fill: 'var(--muted)', opacity: 0.6 }}
                                content={TrendTooltip}
                            />
                            {SERIES.map((series) => (
                                <Bar
                                    key={series.key}
                                    dataKey={series.key}
                                    name={series.label}
                                    fill={series.color}
                                    radius={[4, 4, 0, 0]}
                                    maxBarSize={18}
                                />
                            ))}
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            )}
        </div>
    );
}
