import { useQuery } from '@tanstack/react-query';
import { CalendarCheck, Upload } from 'lucide-react';
import { Link } from 'react-router';
import { Button } from '@/components/ui/button';
import {
    EmptyState,
    ErrorState,
    PageHeader,
    Panel,
    RowsSkeleton,
    StatCard,
    Table,
    Td,
} from '@/components/fund/primitives';
import { StatusBadge } from '@/components/fund/status-badge';
import { useQuery as useReference } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { formatMoney, formatMonth, paiseToRupeesInput } from '@/lib/format';
import type { DuesMonth, DuesSummary, ReferenceData } from '@/lib/types';

export default function MemberDuesPage() {
    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['member', 'dues'],
        queryFn: () =>
            api.get<{ data: { summary: DuesSummary; months: DuesMonth[] } }>(
                '/api/v1/member/dues',
            ),
    });
    const reference = useReference({
        queryKey: ['reference'],
        queryFn: () => api.get<ReferenceData>('/api/v1/reference'),
        staleTime: 300_000,
    });
    const monthlyFundId = reference.data?.funds.find(
        (fund) => fund.is_mandatory,
    )?.id;

    const summary = data?.data.summary;

    return (
        <div className="grid gap-6">
            <PageHeader
                title="Monthly dues"
                description="What is due each month, and how it has been settled. Past months keep the rate they were generated with."
            />

            {error && (
                <ErrorState error={error} onRetry={() => void refetch()} />
            )}

            {summary && (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        tone={
                            summary.outstanding_paise > 0
                                ? 'attention'
                                : 'default'
                        }
                        label="Outstanding"
                        value={formatMoney(summary.outstanding_paise)}
                    />
                    <StatCard
                        label="Paid towards dues"
                        value={formatMoney(summary.paid_paise)}
                    />
                    <StatCard
                        label="Paid in advance"
                        value={formatMoney(summary.advance_paise)}
                        hint="Applied automatically to future months"
                    />
                    <StatCard
                        label="Waived"
                        value={formatMoney(summary.waived_paise)}
                    />
                </div>
            )}

            <Panel flush title="Month by month">
                {isLoading && <RowsSkeleton />}
                {data?.data.months.length === 0 && (
                    <EmptyState
                        icon={<CalendarCheck />}
                        title="No dues yet"
                        description="Your first month's dues will appear here."
                    />
                )}

                {data && data.data.months.length > 0 && (
                    <Table
                        head={[
                            { label: 'Month' },
                            { label: 'Due', className: 'text-right' },
                            {
                                label: 'Paid',
                                className: 'text-right hidden sm:table-cell',
                            },
                            {
                                label: 'Waived',
                                className: 'text-right hidden md:table-cell',
                            },
                            { label: 'Outstanding', className: 'text-right' },
                            { label: 'Status' },
                            { label: '' },
                        ]}
                    >
                        {data.data.months.map((month) => (
                            <tr key={month.id}>
                                <Td className="font-medium whitespace-nowrap text-charcoal">
                                    {formatMonth(month.period)}
                                </Td>
                                <Td className="text-right tabular">
                                    {formatMoney(month.amount_paise)}
                                </Td>
                                <Td className="hidden text-right tabular sm:table-cell">
                                    {formatMoney(month.paid_paise)}
                                </Td>
                                <Td className="hidden text-right tabular md:table-cell">
                                    {formatMoney(month.waived_paise)}
                                </Td>
                                <Td className="text-right font-semibold text-charcoal tabular">
                                    {formatMoney(month.outstanding_paise)}
                                </Td>
                                <Td>
                                    <StatusBadge status={month.status} />
                                </Td>
                                <Td className="text-right">
                                    {month.outstanding_paise > 0 &&
                                        monthlyFundId && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    to={`/pay?fund=${monthlyFundId}&month=${month.period}&amount=${paiseToRupeesInput(month.outstanding_paise)}`}
                                                >
                                                    <Upload /> Pay
                                                </Link>
                                            </Button>
                                        )}
                                </Td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Panel>
        </div>
    );
}
