import {
    keepPreviousData,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import { BookOpen, RotateCcw } from 'lucide-react';
import { Fragment, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { SelectField, TextField } from '@/components/fund/form-fields';
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
import { ReasonDialog } from '@/components/fund/reason-dialog';
import { api } from '@/lib/api';
import {
    formatDate,
    formatDateTime,
    formatMoney,
    transactionTypeLabels,
} from '@/lib/format';
import type { LedgerTransaction, Paginated, ReferenceData } from '@/lib/types';

export default function LedgerPage() {
    const queryClient = useQueryClient();
    const [type, setType] = useState('');
    const [fundId, setFundId] = useState('');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [page, setPage] = useState(1);
    const [expanded, setExpanded] = useState<string | null>(null);
    const [reversing, setReversing] = useState<LedgerTransaction | null>(null);

    const reference = useQuery({
        queryKey: ['reference'],
        queryFn: () => api.get<ReferenceData>('/api/v1/reference'),
        staleTime: 300_000,
    });
    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['admin', 'ledger', type, fundId, from, to, page],
        queryFn: () =>
            api.get<Paginated<LedgerTransaction>>('/api/v1/admin/ledger', {
                type,
                fund_id: fundId,
                from,
                to,
                page,
            }),
        placeholderData: keepPreviousData,
    });

    return (
        <div className="grid gap-6">
            <PageHeader
                title="Ledger"
                description="Every confirmed movement of money. Nothing here can be edited or deleted; a mistake is corrected by reversing it."
            />

            <Panel flush>
                <div className="grid gap-4 px-5 pb-4 sm:grid-cols-2 lg:grid-cols-4">
                    <SelectField
                        label="Type"
                        value={type}
                        onChange={(event) => {
                            setType(event.target.value);
                            setPage(1);
                        }}
                    >
                        <option value="">All types</option>
                        {Object.entries(transactionTypeLabels).map(
                            ([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ),
                        )}
                    </SelectField>
                    <SelectField
                        label="Fund"
                        value={fundId}
                        onChange={(event) => {
                            setFundId(event.target.value);
                            setPage(1);
                        }}
                    >
                        <option value="">All funds</option>
                        {(reference.data?.funds ?? []).map((fund) => (
                            <option key={fund.id} value={fund.id}>
                                {fund.name}
                            </option>
                        ))}
                    </SelectField>
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
                        icon={<BookOpen />}
                        title="No transactions found"
                    />
                )}

                {data && data.data.length > 0 && (
                    <Table
                        head={[
                            { label: 'Date' },
                            { label: 'Type' },
                            { label: 'Description' },
                            { label: 'Amount', className: 'text-right' },
                        ]}
                    >
                        {data.data.map((transaction) => {
                            const isOpen = expanded === transaction.uuid;
                            const total = (transaction.entries ?? []).reduce(
                                (sum, entry) =>
                                    sum +
                                    (transaction.type === 'account_transfer'
                                        ? Math.max(entry.amount_paise, 0)
                                        : entry.amount_paise),
                                0,
                            );

                            return (
                                <Fragment key={transaction.uuid}>
                                    <tr
                                        tabIndex={0}
                                        aria-expanded={isOpen}
                                        className="cursor-pointer outline-none hover:bg-muted/40 focus-visible:bg-muted/60"
                                        onClick={() =>
                                            setExpanded(
                                                isOpen
                                                    ? null
                                                    : transaction.uuid,
                                            )
                                        }
                                        onKeyDown={(event) =>
                                            event.key === 'Enter' &&
                                            setExpanded(
                                                isOpen
                                                    ? null
                                                    : transaction.uuid,
                                            )
                                        }
                                    >
                                        <Td className="whitespace-nowrap">
                                            {formatDate(
                                                transaction.occurred_on,
                                            )}
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {
                                                transactionTypeLabels[
                                                    transaction.type
                                                ]
                                            }
                                            {transaction.reversed_by && (
                                                <span className="ml-1.5 text-xs text-muted-foreground">
                                                    (reversed)
                                                </span>
                                            )}
                                        </Td>
                                        <Td className="text-charcoal">
                                            {transaction.narration}
                                        </Td>
                                        <Td className="text-right font-semibold text-charcoal tabular">
                                            {formatMoney(total)}
                                        </Td>
                                    </tr>
                                    {isOpen && (
                                        <tr className="bg-muted/30">
                                            <td
                                                colSpan={4}
                                                className="px-5 py-4"
                                            >
                                                <div className="grid gap-3 text-sm">
                                                    <ul className="grid gap-1">
                                                        {(
                                                            transaction.entries ??
                                                            []
                                                        ).map((entry) => (
                                                            <li
                                                                key={entry.id}
                                                                className="flex justify-between gap-4"
                                                            >
                                                                <span className="text-muted-foreground">
                                                                    {entry.fund}{' '}
                                                                    ·{' '}
                                                                    {
                                                                        entry.account
                                                                    }
                                                                </span>
                                                                <span className="text-charcoal tabular">
                                                                    {formatMoney(
                                                                        entry.amount_paise,
                                                                    )}
                                                                </span>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                    <p className="text-xs text-muted-foreground">
                                                        Posted by{' '}
                                                        {transaction.posted_by ??
                                                            'the system'}{' '}
                                                        on{' '}
                                                        {formatDateTime(
                                                            transaction.created_at,
                                                        )}
                                                        . Ref:{' '}
                                                        <code className="break-all">
                                                            {transaction.uuid}
                                                        </code>
                                                    </p>
                                                    {!transaction.reversed_by &&
                                                        transaction.type !==
                                                            'reversal' && (
                                                            <div>
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    className="text-destructive"
                                                                    onClick={() =>
                                                                        setReversing(
                                                                            transaction,
                                                                        )
                                                                    }
                                                                >
                                                                    <RotateCcw />{' '}
                                                                    Reverse
                                                                </Button>
                                                            </div>
                                                        )}
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            );
                        })}
                    </Table>
                )}

                <Pagination result={data} page={page} onPage={setPage} />
            </Panel>

            <ReasonDialog
                open={reversing !== null}
                onOpenChange={(open) => !open && setReversing(null)}
                title="Reverse this transaction"
                description="An equal and opposite entry is posted today. The original stays on record. Refused if the money has already been spent."
                label="Reason"
                confirmLabel="Post reversal"
                onConfirm={async (reason) => {
                    await api.post(
                        `/api/v1/admin/ledger/${reversing?.uuid}/reverse`,
                        { reason },
                    );
                    void queryClient.invalidateQueries({ queryKey: ['admin'] });
                    toast.success('Reversal posted.');
                }}
            />
        </div>
    );
}
