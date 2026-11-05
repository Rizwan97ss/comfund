import {
    keepPreviousData,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import { ArrowLeftRight, Landmark, Plus, Scale, Wallet } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    MoneyField,
    SelectField,
    TextField,
} from '@/components/fund/form-fields';
import {
    EmptyState,
    ErrorState,
    FormAlert,
    PageHeader,
    Pagination,
    Panel,
    RowsSkeleton,
    StatCard,
    SubmitButton,
    Table,
    Td,
} from '@/components/fund/primitives';
import { useIdempotencyKey } from '@/hooks/use-idempotency-key';
import { ApiError, api } from '@/lib/api';
import {
    formatDate,
    formatMoney,
    rupeesToPaise,
    todayLocal,
} from '@/lib/format';
import { errorMessage } from '@/lib/forms';
import type { AccountBalance, Paginated, ReferenceData } from '@/lib/types';

type Transfer = {
    uuid: string;
    fund: string;
    from_account: string;
    to_account: string;
    amount_paise: number;
    transfer_date: string;
    created_by: string;
    is_reversed: boolean;
};
type Reconciliation = {
    id: number;
    account: string;
    as_of: string;
    statement_balance_paise: number;
    book_balance_paise: number;
    difference_paise: number;
    created_by: string;
};

/** Shared shell for the small forms on this page. */
function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    submitLabel,
    onSubmit,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    submitLabel: string;
    onSubmit: () => Promise<void>;
    children: (errors: Record<string, string[]>) => ReactNode;
}) {
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [problem, setProblem] = useState<string | null>(null);
    const [isPending, setIsPending] = useState(false);

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        setIsPending(true);
        setErrors({});
        setProblem(null);

        try {
            await onSubmit();
            onOpenChange(false);
        } catch (error) {
            if (error instanceof ApiError && error.isValidation) {
                setErrors(error.errors);
            } else {
                setProblem(errorMessage(error));
            }
        } finally {
            setIsPending(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    <FormAlert message={problem} />
                    {children(errors)}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <SubmitButton pending={isPending}>
                            {submitLabel}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function AccountsPage() {
    const queryClient = useQueryClient();
    const idempotency = useIdempotencyKey();
    const [dialog, setDialog] = useState<
        'account' | 'opening' | 'transfer' | 'reconcile' | null
    >(null);
    const [transferPage, setTransferPage] = useState(1);
    const [fields, setFields] = useState<Record<string, string>>({});

    const reference = useQuery({
        queryKey: ['reference'],
        queryFn: () => api.get<ReferenceData>('/api/v1/reference'),
        staleTime: 300_000,
    });
    const accounts = useQuery({
        queryKey: ['admin', 'accounts'],
        queryFn: () =>
            api.get<{ data: AccountBalance[] }>('/api/v1/admin/accounts'),
    });
    const transfers = useQuery({
        queryKey: ['admin', 'transfers', transferPage],
        queryFn: () =>
            api.get<Paginated<Transfer>>('/api/v1/admin/transfers', {
                page: transferPage,
            }),
        placeholderData: keepPreviousData,
    });
    const reconciliations = useQuery({
        queryKey: ['admin', 'reconciliations'],
        queryFn: () =>
            api.get<Paginated<Reconciliation>>('/api/v1/admin/reconciliations'),
    });

    const activeAccounts = (accounts.data?.data ?? []).filter(
        (account) => account.is_active,
    );
    const funds = reference.data?.funds ?? [];

    function open(which: NonNullable<typeof dialog>) {
        setFields({ date: todayLocal(), type: 'bank' });
        setDialog(which);
    }

    const bind = (name: string) => ({
        value: fields[name] ?? '',
        onChange: (
            event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>,
        ) =>
            setFields((current) => ({
                ...current,
                [name]: event.target.value,
            })),
    });

    function done(message: string) {
        void queryClient.invalidateQueries({ queryKey: ['admin'] });
        toast.success(message);
    }

    function requireAmount(name = 'amount'): number {
        const amount = rupeesToPaise(fields[name] ?? '');

        if (amount === null) {
            throw new Error('Enter a valid amount, like 100 or 250.50.');
        }

        return amount;
    }

    const fundOptions = funds.map((fund) => (
        <option key={fund.id} value={fund.id}>
            {fund.name}
        </option>
    ));
    const accountOptions = activeAccounts.map((account) => (
        <option key={account.id} value={account.id}>
            {account.name}
        </option>
    ));

    return (
        <div className="grid gap-6">
            <PageHeader
                title="Cash & bank"
                description="Where the money physically is. Moving it between accounts is neither income nor expense."
                actions={
                    <>
                        <Button
                            variant="outline"
                            onClick={() => open('account')}
                        >
                            <Plus /> Add account
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => open('opening')}
                        >
                            <Wallet /> Opening balance
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => open('reconcile')}
                        >
                            <Scale /> Reconcile
                        </Button>
                        <Button onClick={() => open('transfer')}>
                            <ArrowLeftRight /> Transfer
                        </Button>
                    </>
                }
            />

            {accounts.error && (
                <ErrorState
                    error={accounts.error}
                    onRetry={() => void accounts.refetch()}
                />
            )}

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {(accounts.data?.data ?? []).map((account) => (
                    <StatCard
                        key={account.id}
                        label={account.name}
                        value={formatMoney(account.balance_paise)}
                        icon={
                            account.type === 'cash' ? <Wallet /> : <Landmark />
                        }
                        hint={
                            account.type === 'cash'
                                ? 'Cash'
                                : account.type === 'bank'
                                  ? 'Bank account'
                                  : 'Digital wallet'
                        }
                    />
                ))}
            </div>

            <Panel flush title="Transfers">
                {transfers.isLoading && <RowsSkeleton rows={3} />}
                {transfers.data?.data.length === 0 && (
                    <EmptyState
                        icon={<ArrowLeftRight />}
                        title="No transfers yet"
                    />
                )}
                {transfers.data && transfers.data.data.length > 0 && (
                    <Table
                        head={[
                            { label: 'Date' },
                            { label: 'Fund' },
                            { label: 'From → To' },
                            { label: 'By', className: 'hidden md:table-cell' },
                            { label: 'Amount', className: 'text-right' },
                        ]}
                    >
                        {transfers.data.data.map((transfer) => (
                            <tr key={transfer.uuid}>
                                <Td className="whitespace-nowrap">
                                    {formatDate(transfer.transfer_date)}
                                </Td>
                                <Td className="text-charcoal">
                                    {transfer.fund}
                                </Td>
                                <Td>
                                    {transfer.from_account} →{' '}
                                    {transfer.to_account}
                                    {transfer.is_reversed && (
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            (reversed)
                                        </span>
                                    )}
                                </Td>
                                <Td className="hidden md:table-cell">
                                    {transfer.created_by}
                                </Td>
                                <Td className="text-right font-semibold text-charcoal tabular">
                                    {formatMoney(transfer.amount_paise)}
                                </Td>
                            </tr>
                        ))}
                    </Table>
                )}
                <Pagination
                    result={transfers.data}
                    page={transferPage}
                    onPage={setTransferPage}
                />
            </Panel>

            <Panel
                flush
                title="Reconciliations"
                description="Bank statements and cash counts compared with the books."
            >
                {reconciliations.data?.data.length === 0 && (
                    <EmptyState
                        icon={<Scale />}
                        title="No reconciliations recorded"
                    />
                )}
                {reconciliations.data &&
                    reconciliations.data.data.length > 0 && (
                        <Table
                            head={[
                                { label: 'As of' },
                                { label: 'Account' },
                                { label: 'Statement', className: 'text-right' },
                                {
                                    label: 'Books',
                                    className:
                                        'text-right hidden sm:table-cell',
                                },
                                {
                                    label: 'Difference',
                                    className: 'text-right',
                                },
                            ]}
                        >
                            {reconciliations.data.data.map((item) => (
                                <tr key={item.id}>
                                    <Td className="whitespace-nowrap">
                                        {formatDate(item.as_of)}
                                    </Td>
                                    <Td className="text-charcoal">
                                        {item.account}
                                    </Td>
                                    <Td className="text-right tabular">
                                        {formatMoney(
                                            item.statement_balance_paise,
                                        )}
                                    </Td>
                                    <Td className="hidden text-right tabular sm:table-cell">
                                        {formatMoney(item.book_balance_paise)}
                                    </Td>
                                    <Td
                                        className={`text-right font-semibold tabular ${item.difference_paise === 0 ? 'text-emerald-ink' : 'text-destructive'}`}
                                    >
                                        {item.difference_paise === 0
                                            ? 'Balanced'
                                            : formatMoney(
                                                  item.difference_paise,
                                              )}
                                    </Td>
                                </tr>
                            ))}
                        </Table>
                    )}
            </Panel>

            <FormDialog
                open={dialog === 'account'}
                onOpenChange={(value) => !value && setDialog(null)}
                title="Add an account"
                description="A cash box, bank account or digital wallet."
                submitLabel="Add account"
                onSubmit={async () => {
                    await api.post('/api/v1/admin/accounts', {
                        name: fields.name,
                        type: fields.type,
                        account_reference: fields.reference || null,
                    });
                    done('Account added.');
                }}
            >
                {(errors) => (
                    <>
                        <TextField
                            label="Name"
                            required
                            error={errors.name?.[0]}
                            {...bind('name')}
                        />
                        <SelectField
                            label="Type"
                            error={errors.type?.[0]}
                            {...bind('type')}
                        >
                            <option value="bank">Bank account</option>
                            <option value="cash">Cash</option>
                            <option value="digital">Digital wallet</option>
                        </SelectField>
                        <TextField
                            label="Reference"
                            optional
                            hint="For example, the last four digits of the account number. Do not enter the full number."
                            error={errors.account_reference?.[0]}
                            {...bind('reference')}
                        />
                    </>
                )}
            </FormDialog>

            <FormDialog
                open={dialog === 'opening'}
                onOpenChange={(value) => !value && setDialog(null)}
                title="Record an opening balance"
                description="Money the community already held before using this system. Only one per fund and account."
                submitLabel="Record balance"
                onSubmit={async () => {
                    await api.post('/api/v1/admin/opening-balances', {
                        fund_id: fields.fund,
                        financial_account_id: fields.account,
                        amount_paise: requireAmount(),
                        as_of: fields.date,
                        note: fields.note || null,
                    });
                    done('Opening balance recorded.');
                }}
            >
                {(errors) => (
                    <>
                        <SelectField
                            label="Fund"
                            required
                            error={errors.fund_id?.[0]}
                            {...bind('fund')}
                        >
                            <option value="">Choose…</option>
                            {fundOptions}
                        </SelectField>
                        <SelectField
                            label="Account"
                            required
                            error={errors.financial_account_id?.[0]}
                            {...bind('account')}
                        >
                            <option value="">Choose…</option>
                            {accountOptions}
                        </SelectField>
                        <MoneyField
                            label="Amount"
                            required
                            error={errors.amount_paise?.[0]}
                            {...bind('amount')}
                        />
                        <TextField
                            label="As of"
                            type="date"
                            max={todayLocal()}
                            required
                            error={errors.as_of?.[0]}
                            {...bind('date')}
                        />
                        <TextField label="Note" optional {...bind('note')} />
                    </>
                )}
            </FormDialog>

            <FormDialog
                open={dialog === 'transfer'}
                onOpenChange={(value) => !value && setDialog(null)}
                title="Transfer between accounts"
                description="Moves one fund's money from one account to another. Money cannot move between funds."
                submitLabel="Transfer"
                onSubmit={async () => {
                    await api.post(
                        '/api/v1/admin/transfers',
                        {
                            fund_id: fields.fund,
                            from_account_id: fields.from,
                            to_account_id: fields.to,
                            amount_paise: requireAmount(),
                            transfer_date: fields.date,
                            transaction_reference: fields.reference || null,
                            note: fields.note || null,
                        },
                        idempotency.current(),
                    );
                    idempotency.renew();
                    done('Transfer recorded.');
                }}
            >
                {(errors) => (
                    <>
                        <SelectField
                            label="Fund"
                            required
                            error={errors.fund_id?.[0]}
                            {...bind('fund')}
                        >
                            <option value="">Choose…</option>
                            {fundOptions}
                        </SelectField>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <SelectField
                                label="From"
                                required
                                error={errors.from_account_id?.[0]}
                                {...bind('from')}
                            >
                                <option value="">Choose…</option>
                                {accountOptions}
                            </SelectField>
                            <SelectField
                                label="To"
                                required
                                error={errors.to_account_id?.[0]}
                                {...bind('to')}
                            >
                                <option value="">Choose…</option>
                                {accountOptions}
                            </SelectField>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <MoneyField
                                label="Amount"
                                required
                                error={errors.amount_paise?.[0]}
                                {...bind('amount')}
                            />
                            <TextField
                                label="Date"
                                type="date"
                                max={todayLocal()}
                                required
                                error={errors.transfer_date?.[0]}
                                {...bind('date')}
                            />
                        </div>
                        <TextField
                            label="Reference"
                            optional
                            error={errors.transaction_reference?.[0]}
                            {...bind('reference')}
                        />
                    </>
                )}
            </FormDialog>

            <FormDialog
                open={dialog === 'reconcile'}
                onOpenChange={(value) => !value && setDialog(null)}
                title="Reconcile an account"
                description="Enter the balance from a bank statement or a cash count. The difference from the books is recorded."
                submitLabel="Record reconciliation"
                onSubmit={async () => {
                    const response = await api.post<{
                        data: { difference_paise: number };
                    }>('/api/v1/admin/reconciliations', {
                        financial_account_id: fields.account,
                        as_of: fields.date,
                        statement_balance_paise:
                            rupeesToPaise(fields.amount ?? '') ??
                            (fields.amount === '0' ? 0 : -1),
                        note: fields.note || null,
                    });
                    done(
                        response.data.difference_paise === 0
                            ? 'The account balances.'
                            : `Difference of ${formatMoney(response.data.difference_paise)} recorded.`,
                    );
                }}
            >
                {(errors) => (
                    <>
                        <SelectField
                            label="Account"
                            required
                            error={errors.financial_account_id?.[0]}
                            {...bind('account')}
                        >
                            <option value="">Choose…</option>
                            {accountOptions}
                        </SelectField>
                        <MoneyField
                            label="Statement balance"
                            required
                            error={errors.statement_balance_paise?.[0]}
                            {...bind('amount')}
                        />
                        <TextField
                            label="As of"
                            type="date"
                            max={todayLocal()}
                            required
                            error={errors.as_of?.[0]}
                            {...bind('date')}
                        />
                        <TextField label="Note" optional {...bind('note')} />
                    </>
                )}
            </FormDialog>
        </div>
    );
}
