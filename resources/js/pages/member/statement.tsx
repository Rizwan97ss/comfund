import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { ScrollText } from 'lucide-react';
import { useState } from 'react';
import { TextField } from '@/components/fund/form-fields';
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
import { api } from '@/lib/api';
import { formatDate, formatMoney, transactionTypeLabels } from '@/lib/format';
import type { LedgerEntry, Paginated } from '@/lib/types';

export default function MemberStatementPage() {
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [page, setPage] = useState(1);

    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['member', 'statement', from, to, page],
        queryFn: () =>
            api.get<Paginated<LedgerEntry>>('/api/v1/member/statement', {
                from,
                to,
                page,
            }),
        placeholderData: keepPreviousData,
    });

    return (
        <div className="grid gap-6">
            <PageHeader
                title="My statement"
                description="Only confirmed money appears here. Reversals show as negative lines, so the history stays complete."
            />

            <Panel flush>
                <div className="grid gap-4 px-5 pb-4 sm:max-w-md sm:grid-cols-2">
                    <TextField
                        label="From"
                        type="date"
                        value={from}
                        onChange={(event) => {
                            setFrom(event.target.value);
                            setPage(1);
                        }}
                    />
                    <TextField
                        label="To"
                        type="date"
                        value={to}
                        onChange={(event) => {
                            setTo(event.target.value);
                            setPage(1);
                        }}
                    />
                </div>

                {isLoading && <RowsSkeleton />}
                {error && (
                    <ErrorState error={error} onRetry={() => void refetch()} />
                )}
                {data?.data.length === 0 && (
                    <EmptyState
                        icon={<ScrollText />}
                        title="Nothing in this period"
                        description="Confirmed payments will be listed here."
                    />
                )}

                {data && data.data.length > 0 && (
                    <Table
                        head={[
                            { label: 'Date' },
                            { label: 'Fund' },
                            {
                                label: 'Type',
                                className: 'hidden sm:table-cell',
                            },
                            { label: 'Amount', className: 'text-right' },
                        ]}
                    >
                        {data.data.map((entry) => (
                            <tr key={entry.id}>
                                <Td className="whitespace-nowrap">
                                    {formatDate(entry.occurred_on)}
                                </Td>
                                <Td className="font-medium text-charcoal">
                                    {entry.fund}
                                </Td>
                                <Td className="hidden sm:table-cell">
                                    {transactionTypeLabels[
                                        entry.transaction_type ?? ''
                                    ] ?? entry.transaction_type}
                                </Td>
                                <Td className="text-right font-semibold text-charcoal tabular">
                                    {formatMoney(entry.amount_paise)}
                                </Td>
                            </tr>
                        ))}
                    </Table>
                )}

                <Pagination result={data} page={page} onPage={setPage} />
            </Panel>
        </div>
    );
}
