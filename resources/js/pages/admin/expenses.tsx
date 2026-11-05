import {
    keepPreviousData,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import { Plus, ReceiptText, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { DocumentDownload } from '@/components/fund/document-link';
import { SelectField, TextField } from '@/components/fund/form-fields';
import {
    DetailList,
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
import { StatusBadge } from '@/components/fund/status-badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { api } from '@/lib/api';
import { formatDate, formatMoney, paymentMethodLabels } from '@/lib/format';
import type { Expense, Paginated, ReferenceData } from '@/lib/types';

export default function ExpensesPage() {
    const queryClient = useQueryClient();
    const [fundId, setFundId] = useState('');
    const [beneficiary, setBeneficiary] = useState('');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [page, setPage] = useState(1);
    const [selected, setSelected] = useState<Expense | null>(null);
    const [reversing, setReversing] = useState<Expense | null>(null);

    const reference = useQuery({
        queryKey: ['reference'],
        queryFn: () => api.get<ReferenceData>('/api/v1/reference'),
        staleTime: 300_000,
    });
    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['admin', 'expenses', fundId, beneficiary, from, to, page],
        queryFn: () =>
            api.get<Paginated<Expense>>('/api/v1/admin/expenses', {
                fund_id: fundId,
                beneficiary,
                from,
                to,
                page,
            }),
        placeholderData: keepPreviousData,
    });

    return (
        <div className="grid gap-6">
            <PageHeader
                title="Expenses"
                description="Spending is refused if the fund's balance in the chosen account is too low."
                actions={
                    <Button asChild>
                        <Link to="/admin/expenses/new">
                            <Plus /> Record an expense
                        </Link>
                    </Button>
                }
            />

            <Panel flush>
                <div className="grid gap-4 px-5 pb-4 sm:grid-cols-2 lg:grid-cols-4">
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
                        label="Beneficiary"
                        type="search"
                        value={beneficiary}
                        onChange={(event) => {
                            setBeneficiary(event.target.value);
                            setPage(1);
                        }}
                    />
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
                        icon={<ReceiptText />}
                        title="No expenses found"
                    />
                )}

                {data && data.data.length > 0 && (
                    <Table
                        head={[
                            { label: 'Date' },
                            { label: 'Beneficiary' },
                            {
                                label: 'Fund',
                                className: 'hidden md:table-cell',
                            },
                            {
                                label: 'Purpose',
                                className: 'hidden lg:table-cell',
                            },
                            { label: 'Amount', className: 'text-right' },
                        ]}
                    >
                        {data.data.map((expense) => (
                            <tr
                                key={expense.uuid}
                                tabIndex={0}
                                className="cursor-pointer outline-none hover:bg-muted/40 focus-visible:bg-muted/60"
                                onClick={() => setSelected(expense)}
                                onKeyDown={(event) =>
                                    event.key === 'Enter' &&
                                    setSelected(expense)
                                }
                            >
                                <Td className="whitespace-nowrap">
                                    {formatDate(expense.expense_date)}
                                </Td>
                                <Td className="font-medium text-charcoal">
                                    {expense.beneficiary}{' '}
                                    {expense.is_reversed && (
                                        <StatusBadge status="reversed" />
                                    )}
                                </Td>
                                <Td className="hidden md:table-cell">
                                    {expense.fund}
                                </Td>
                                <Td className="hidden lg:table-cell">
                                    {expense.purpose}
                                </Td>
                                <Td className="text-right font-semibold text-charcoal tabular">
                                    {formatMoney(expense.amount_paise)}
                                </Td>
                            </tr>
                        ))}
                    </Table>
                )}

                <Pagination result={data} page={page} onPage={setPage} />
            </Panel>

            <Dialog
                open={selected !== null}
                onOpenChange={(open) => !open && setSelected(null)}
            >
                <DialogContent className="sm:max-w-xl">
                    {selected && (
                        <div className="grid gap-5">
                            <DialogHeader>
                                <DialogTitle>
                                    {formatMoney(selected.amount_paise)} to{' '}
                                    {selected.beneficiary}
                                </DialogTitle>
                                <DialogDescription>
                                    {selected.fund}
                                </DialogDescription>
                            </DialogHeader>
                            <DetailList
                                items={[
                                    ['Purpose', selected.purpose],
                                    ['Date', formatDate(selected.expense_date)],
                                    ['Paid from', selected.account],
                                    [
                                        'Method',
                                        paymentMethodLabels[
                                            selected.payment_method
                                        ],
                                    ],
                                    [
                                        'Reference',
                                        selected.transaction_reference ?? '—',
                                    ],
                                    ['Recorded by', selected.created_by],
                                    [
                                        'Description',
                                        selected.description ?? '—',
                                    ],
                                ]}
                            />
                            {selected.documents.length > 0 && (
                                <div className="flex flex-wrap gap-2">
                                    {selected.documents.map((file) => (
                                        <DocumentDownload
                                            key={file.uuid}
                                            document={file}
                                        />
                                    ))}
                                </div>
                            )}
                            {selected.is_reversed ? (
                                <StatusBadge status="reversed" />
                            ) : (
                                <div>
                                    <Button
                                        variant="outline"
                                        className="text-destructive"
                                        onClick={() => {
                                            setReversing(selected);
                                            setSelected(null);
                                        }}
                                    >
                                        <RotateCcw /> Reverse this expense
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <ReasonDialog
                open={reversing !== null}
                onOpenChange={(open) => !open && setReversing(null)}
                title="Reverse this expense"
                description="The money returns to the fund. The original expense and this reversal both stay on record."
                label="Reason"
                confirmLabel="Reverse expense"
                onConfirm={async (reason) => {
                    await api.post(
                        `/api/v1/admin/ledger/${reversing?.ledger_uuid}/reverse`,
                        { reason },
                    );
                    void queryClient.invalidateQueries({ queryKey: ['admin'] });
                    toast.success('Expense reversed.');
                }}
            />
        </div>
    );
}
