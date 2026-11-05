import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { ClipboardCheck, Paperclip } from 'lucide-react';
import { useNavigate, useSearchParams } from 'react-router';
import { Button } from '@/components/ui/button';
import {
    EmptyState,
    ErrorState,
    PageHeader,
    Pagination,
    Panel,
    RowsSkeleton,
    Table,
    Td,
} from '@/components/fund/primitives';
import { StatusBadge } from '@/components/fund/status-badge';
import { api } from '@/lib/api';
import {
    formatDate,
    formatMoney,
    formatMonth,
    paymentMethodLabels,
} from '@/lib/format';
import type { Paginated, Submission } from '@/lib/types';

const FILTERS = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
];

export default function ApprovalsPage() {
    const navigate = useNavigate();
    const [searchParams, setSearchParams] = useSearchParams();
    const status = searchParams.get('status') ?? 'pending';
    const page = Number(searchParams.get('page') ?? 1);

    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['admin', 'submissions', status, page],
        queryFn: () =>
            api.get<Paginated<Submission>>('/api/v1/admin/submissions', {
                status,
                page,
            }),
        placeholderData: keepPreviousData,
    });

    return (
        <div className="grid gap-6">
            <PageHeader
                title="Approvals"
                description="Check each payment against its receipt. Approving posts it to the ledger permanently."
            />

            <Panel flush>
                <div
                    className="flex flex-wrap gap-1.5 px-5 pb-4"
                    role="group"
                    aria-label="Filter by status"
                >
                    {FILTERS.map((filter) => (
                        <Button
                            key={filter.value}
                            size="sm"
                            variant={
                                status === filter.value ? 'default' : 'outline'
                            }
                            aria-pressed={status === filter.value}
                            onClick={() =>
                                setSearchParams({ status: filter.value })
                            }
                        >
                            {filter.label}
                        </Button>
                    ))}
                </div>

                {isLoading && <RowsSkeleton />}
                {error && (
                    <ErrorState error={error} onRetry={() => void refetch()} />
                )}
                {data?.data.length === 0 && (
                    <EmptyState
                        icon={<ClipboardCheck />}
                        title={
                            status === 'pending'
                                ? 'Nothing waiting for review'
                                : 'Nothing here'
                        }
                        description={
                            status === 'pending'
                                ? 'New member payments will appear here.'
                                : undefined
                        }
                    />
                )}

                {data && data.data.length > 0 && (
                    <Table
                        head={[
                            { label: 'Member' },
                            { label: 'Fund' },
                            {
                                label: 'Paid on',
                                className: 'hidden md:table-cell',
                            },
                            {
                                label: 'Method',
                                className: 'hidden lg:table-cell',
                            },
                            { label: 'Amount', className: 'text-right' },
                            { label: 'Status' },
                        ]}
                    >
                        {data.data.map((item) => (
                            <tr
                                key={item.uuid}
                                tabIndex={0}
                                className="cursor-pointer outline-none hover:bg-muted/40 focus-visible:bg-muted/60"
                                onClick={() =>
                                    void navigate(
                                        `/admin/approvals/${item.uuid}`,
                                    )
                                }
                                onKeyDown={(event) =>
                                    event.key === 'Enter' &&
                                    void navigate(
                                        `/admin/approvals/${item.uuid}`,
                                    )
                                }
                            >
                                <Td>
                                    <p className="font-medium text-charcoal">
                                        {item.member?.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {item.member?.member_code}
                                    </p>
                                </Td>
                                <Td>
                                    <p className="text-charcoal">
                                        {item.fund.name}
                                    </p>
                                    {item.applicable_month && (
                                        <p className="text-xs text-muted-foreground">
                                            {formatMonth(item.applicable_month)}
                                        </p>
                                    )}
                                </Td>
                                <Td className="hidden whitespace-nowrap md:table-cell">
                                    {formatDate(item.payment_date)}
                                </Td>
                                <Td className="hidden lg:table-cell">
                                    <span className="flex items-center gap-1.5">
                                        {
                                            paymentMethodLabels[
                                                item.payment_method
                                            ]
                                        }
                                        {item.document && (
                                            <Paperclip
                                                className="size-3.5 text-muted-foreground"
                                                aria-label="Has a receipt"
                                            />
                                        )}
                                    </span>
                                </Td>
                                <Td className="text-right font-semibold text-charcoal tabular">
                                    {formatMoney(item.amount_paise)}
                                </Td>
                                <Td>
                                    <StatusBadge
                                        status={
                                            item.is_reversed
                                                ? 'reversed'
                                                : item.status
                                        }
                                    />
                                </Td>
                            </tr>
                        ))}
                    </Table>
                )}

                <Pagination
                    result={data}
                    page={page}
                    onPage={(next) =>
                        setSearchParams({ status, page: String(next) })
                    }
                />
            </Panel>
        </div>
    );
}
