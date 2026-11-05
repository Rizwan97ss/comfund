import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { CheckCircle2, Printer, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { SelectField, TextField } from '@/components/fund/form-fields';
import {
    ErrorState,
    PageHeader,
    Panel,
    RowsSkeleton,
    Table,
    Td,
} from '@/components/fund/primitives';
import { api } from '@/lib/api';
import {
    currentMonth,
    formatDate,
    formatMoney,
    todayLocal,
} from '@/lib/format';
import type { Statement, StatementRow } from '@/lib/types';

function monthRange(month: string): [string, string] {
    const [year, monthNumber] = month.split('-').map(Number);
    const lastDay = new Date(year, monthNumber, 0).getDate();

    return [`${month}-01`, `${month}-${String(lastDay).padStart(2, '0')}`];
}

function StatementTable({
    rows,
    showTransfers,
}: {
    rows: StatementRow[];
    showTransfers: boolean;
}) {
    const total = (key: keyof StatementRow) =>
        rows.reduce((sum, row) => sum + Number(row[key]), 0);
    const columns: {
        key: keyof StatementRow;
        label: string;
        hide?: boolean;
    }[] = [
        { key: 'opening_paise', label: 'Opening' },
        { key: 'opening_entries_paise', label: 'Brought in' },
        { key: 'collections_paise', label: 'Collections' },
        { key: 'expenses_paise', label: 'Expenses' },
        {
            key: 'transfers_net_paise',
            label: 'Transfers',
            hide: !showTransfers,
        },
        { key: 'closing_paise', label: 'Closing' },
    ];
    const visible = columns.filter((column) => !column.hide);

    return (
        <Table
            head={[
                { label: 'Name' },
                ...visible.map((column) => ({
                    label: column.label,
                    className: 'text-right',
                })),
            ]}
        >
            {rows.map((row) => (
                <tr key={row.id}>
                    <Td className="font-medium whitespace-nowrap text-charcoal">
                        {row.name}
                    </Td>
                    {visible.map((column) => (
                        <Td
                            key={column.key}
                            className={`text-right whitespace-nowrap tabular ${column.key === 'closing_paise' ? 'font-semibold text-charcoal' : ''}`}
                        >
                            {formatMoney(Number(row[column.key]))}
                        </Td>
                    ))}
                </tr>
            ))}
            <tr className="bg-muted/50 font-semibold text-charcoal">
                <Td>Total</Td>
                {visible.map((column) => (
                    <Td
                        key={column.key}
                        className="text-right whitespace-nowrap tabular"
                    >
                        {formatMoney(total(column.key))}
                    </Td>
                ))}
            </tr>
        </Table>
    );
}

export default function ReportsPage() {
    const [period, setPeriod] = useState<'month' | 'year' | 'custom'>('month');
    const [month, setMonth] = useState(currentMonth());
    const [year, setYear] = useState(todayLocal().slice(0, 4));
    const [customFrom, setCustomFrom] = useState(
        `${todayLocal().slice(0, 4)}-01-01`,
    );
    const [customTo, setCustomTo] = useState(todayLocal());

    const [from, to] =
        period === 'month'
            ? monthRange(month)
            : period === 'year'
              ? [`${year}-01-01`, `${year}-12-31`]
              : [customFrom, customTo];

    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['admin', 'statement', from, to],
        queryFn: () =>
            api.get<{ data: Statement }>('/api/v1/admin/reports/statement', {
                from,
                to,
            }),
        placeholderData: keepPreviousData,
        enabled: Boolean(from && to),
    });

    const integrity = useQuery({
        queryKey: ['admin', 'integrity'],
        queryFn: () =>
            api.get<{
                data: {
                    balanced: boolean;
                    ledger_total_paise: number;
                    differences: unknown[];
                };
            }>('/api/v1/admin/reports/integrity'),
    });

    return (
        <div className="grid gap-6">
            <PageHeader
                title="Financial statements"
                description={`Opening balances, collections, expenses and closing balances from ${formatDate(from)} to ${formatDate(to)}.`}
                actions={
                    <Button variant="outline" onClick={() => window.print()}>
                        <Printer /> Print
                    </Button>
                }
            />

            {integrity.data && (
                <div
                    role="status"
                    className={`flex items-center gap-3 rounded-xl border px-4 py-3 text-sm ${integrity.data.data.balanced ? 'border-emerald-deep/20 bg-emerald-soft text-emerald-ink' : 'border-destructive/30 bg-danger-soft text-danger-ink'}`}
                >
                    {integrity.data.data.balanced ? (
                        <CheckCircle2 className="size-5 shrink-0" />
                    ) : (
                        <XCircle className="size-5 shrink-0" />
                    )}
                    {integrity.data.data.balanced ? (
                        <span>
                            Books verified: every running balance matches a full
                            recomputation from the ledger, totalling{' '}
                            <strong className="tabular">
                                {formatMoney(
                                    integrity.data.data.ledger_total_paise,
                                )}
                            </strong>
                            .
                        </span>
                    ) : (
                        <span>
                            <strong>Investigate immediately:</strong>{' '}
                            {integrity.data.data.differences.length} running
                            balances do not match the ledger.
                        </span>
                    )}
                </div>
            )}

            <Panel>
                <div className="grid gap-4 sm:grid-cols-3 print:hidden">
                    <SelectField
                        label="Period"
                        value={period}
                        onChange={(event) =>
                            setPeriod(event.target.value as typeof period)
                        }
                    >
                        <option value="month">Monthly</option>
                        <option value="year">Yearly</option>
                        <option value="custom">Custom range</option>
                    </SelectField>
                    {period === 'month' && (
                        <TextField
                            label="Month"
                            type="month"
                            value={month}
                            onChange={(event) => setMonth(event.target.value)}
                        />
                    )}
                    {period === 'year' && (
                        <TextField
                            label="Year"
                            type="number"
                            min="2000"
                            max="2100"
                            value={year}
                            onChange={(event) => setYear(event.target.value)}
                        />
                    )}
                    {period === 'custom' && (
                        <>
                            <TextField
                                label="From"
                                type="date"
                                value={customFrom}
                                onChange={(event) =>
                                    setCustomFrom(event.target.value)
                                }
                            />
                            <TextField
                                label="To"
                                type="date"
                                value={customTo}
                                onChange={(event) =>
                                    setCustomTo(event.target.value)
                                }
                            />
                        </>
                    )}
                </div>
            </Panel>

            {isLoading && <RowsSkeleton />}
            {error && (
                <ErrorState error={error} onRetry={() => void refetch()} />
            )}

            {data && (
                <>
                    <Panel flush title="By fund">
                        <StatementTable
                            rows={data.data.funds}
                            showTransfers={false}
                        />
                    </Panel>
                    <Panel
                        flush
                        title="By account"
                        description="The same money, by where it is kept. Transfers net to zero across accounts."
                    >
                        <StatementTable
                            rows={data.data.accounts}
                            showTransfers
                        />
                    </Panel>
                </>
            )}
        </div>
    );
}
