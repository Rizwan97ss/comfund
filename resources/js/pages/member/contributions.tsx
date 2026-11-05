import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { HandCoins, Upload } from 'lucide-react';
import { Link, useNavigate, useSearchParams } from 'react-router';
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
    { value: '', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
];

export default function ContributionsPage() {
    const navigate = useNavigate();
    const [searchParams, setSearchParams] = useSearchParams();
    const status = searchParams.get('status') ?? '';
    const page = Number(searchParams.get('page') ?? 1);

    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['member', 'submissions', status, page],
        queryFn: () =>
            api.get<Paginated<Submission>>('/api/v1/member/submissions', {
                status,
                page,
            }),
        placeholderData: keepPreviousData,
    });

    return (
        <div className="grid gap-6">
            <PageHeader
                title="My contributions"
                description="Everything you have submitted, and what happened to it."
                actions={
                    <Button asChild>
                        <Link to="/pay">
                            <Upload /> Submit a payment
                        </Link>
                    </Button>
                }
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
                                setSearchParams(
                                    filter.value
                                        ? { status: filter.value }
                                        : {},
                                )
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
                        icon={<HandCoins />}
                        title="No payments here yet"
                        description="When you submit a payment it will appear here."
                    />
                )}

                {data && data.data.length > 0 && (
                    <Table
                        head={[
                            { label: 'Date' },
                            { label: 'Fund' },
                            {
                                label: 'Month',
                                className: 'hidden md:table-cell',
                            },
                            {
                                label: 'Method',
                                className: 'hidden md:table-cell',
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
                                    void navigate(`/contributions/${item.uuid}`)
                                }
                                onKeyDown={(event) =>
                                    event.key === 'Enter' &&
                                    void navigate(`/contributions/${item.uuid}`)
                                }
                            >
                                <Td className="whitespace-nowrap">
                                    {formatDate(item.payment_date)}
                                </Td>
                                <Td className="font-medium text-charcoal">
                                    {item.fund.name}
                                </Td>
                                <Td className="hidden whitespace-nowrap md:table-cell">
                                    {item.applicable_month
                                        ? formatMonth(item.applicable_month)
                                        : '—'}
                                </Td>
                                <Td className="hidden md:table-cell">
                                    {paymentMethodLabels[item.payment_method]}
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
                        setSearchParams({
                            ...(status ? { status } : {}),
                            page: String(next),
                        })
                    }
                />
            </Panel>
        </div>
    );
}
