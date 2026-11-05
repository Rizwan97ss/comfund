import {
    keepPreviousData,
    useMutation,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import { CalendarCheck, CheckCircle2, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    MoneyField,
    TextAreaField,
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
    SubmitButton,
    Table,
    Td,
} from '@/components/fund/primitives';
import { ReasonDialog } from '@/components/fund/reason-dialog';
import { ApiError, api } from '@/lib/api';
import {
    currentMonth,
    formatDateTime,
    formatMoney,
    formatMonth,
    rupeesToPaise,
} from '@/lib/format';
import { errorMessage } from '@/lib/forms';
import type { Member, Paginated } from '@/lib/types';

type Rate = {
    id: number;
    amount_paise: number;
    effective_from: string;
    note: string | null;
    created_by: string | null;
};
type Waiver = {
    id: number;
    member: string;
    period: string;
    amount_paise: number;
    reason: string;
    granted_by: string;
    is_reversal: boolean;
    is_reversed: boolean;
    created_at: string | null;
};

export default function AdminDuesPage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [waiverPage, setWaiverPage] = useState(1);
    const [amount, setAmount] = useState('');
    const [effectiveMonth, setEffectiveMonth] = useState(currentMonth());
    const [note, setNote] = useState('');
    const [rateErrors, setRateErrors] = useState<Record<string, string[]>>({});
    const [rateProblem, setRateProblem] = useState<string | null>(null);
    const [reversing, setReversing] = useState<Waiver | null>(null);

    const unpaid = useQuery({
        queryKey: ['admin', 'dues', 'unpaid', page],
        queryFn: () =>
            api.get<Paginated<Member>>('/api/v1/admin/dues/unpaid', { page }),
        placeholderData: keepPreviousData,
    });
    const rates = useQuery({
        queryKey: ['admin', 'dues', 'rates'],
        queryFn: () => api.get<{ data: Rate[] }>('/api/v1/admin/dues/rates'),
    });
    const waivers = useQuery({
        queryKey: ['admin', 'dues', 'waivers', waiverPage],
        queryFn: () =>
            api.get<Paginated<Waiver>>('/api/v1/admin/dues/waivers', {
                page: waiverPage,
            }),
        placeholderData: keepPreviousData,
    });

    const saveRate = useMutation({
        mutationFn: () =>
            api.post('/api/v1/admin/dues/rates', {
                amount_paise: rupeesToPaise(amount),
                effective_month: effectiveMonth,
                note,
            }),
        onSuccess: () => {
            setAmount('');
            setNote('');
            setRateErrors({});
            setRateProblem(null);
            void queryClient.invalidateQueries({
                queryKey: ['admin', 'dues', 'rates'],
            });
            toast.success(
                'New rate saved. Dues already generated keep their amount.',
            );
        },
        onError: (error) => {
            if (error instanceof ApiError && error.isValidation) {
                setRateErrors(error.errors);
            } else {
                setRateProblem(errorMessage(error));
            }
        },
    });

    const generate = useMutation({
        mutationFn: () =>
            api.post('/api/v1/admin/dues/generate', { period: currentMonth() }),
        onSuccess: () =>
            toast.success(
                'Dues generation started. It is safe to run more than once.',
            ),
        onError: (error) => toast.error(errorMessage(error)),
    });

    return (
        <div className="grid gap-6">
            <PageHeader
                title="Monthly dues"
                description="Dues generate automatically on the 1st of each month."
                actions={
                    <Button
                        variant="outline"
                        onClick={() => generate.mutate()}
                        disabled={generate.isPending}
                    >
                        <CalendarCheck /> Generate {formatMonth(currentMonth())}
                    </Button>
                }
            />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                <Panel
                    flush
                    title="Members in arrears"
                    description="Largest balances first."
                >
                    {unpaid.isLoading && <RowsSkeleton />}
                    {unpaid.error && (
                        <ErrorState
                            error={unpaid.error}
                            onRetry={() => void unpaid.refetch()}
                        />
                    )}
                    {unpaid.data?.data.length === 0 && (
                        <EmptyState
                            icon={<CheckCircle2 />}
                            title="Everyone is up to date"
                        />
                    )}
                    {unpaid.data && unpaid.data.data.length > 0 && (
                        <Table
                            head={[
                                { label: 'Member' },
                                {
                                    label: 'Outstanding',
                                    className: 'text-right',
                                },
                            ]}
                        >
                            {unpaid.data.data.map((member) => (
                                <tr
                                    key={member.id}
                                    tabIndex={0}
                                    className="cursor-pointer outline-none hover:bg-muted/40 focus-visible:bg-muted/60"
                                    onClick={() =>
                                        void navigate(
                                            `/admin/members/${member.id}`,
                                        )
                                    }
                                    onKeyDown={(event) =>
                                        event.key === 'Enter' &&
                                        void navigate(
                                            `/admin/members/${member.id}`,
                                        )
                                    }
                                >
                                    <Td>
                                        <p className="font-medium text-charcoal">
                                            {member.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {member.member_code}
                                        </p>
                                    </Td>
                                    <Td className="text-right font-semibold text-charcoal tabular">
                                        {formatMoney(
                                            member.outstanding_paise ?? 0,
                                        )}
                                    </Td>
                                </tr>
                            ))}
                        </Table>
                    )}
                    <Pagination
                        result={unpaid.data}
                        page={page}
                        onPage={setPage}
                    />
                </Panel>

                <div className="grid content-start gap-6">
                    <Panel
                        title="Change the monthly rate"
                        description="Applies from the chosen month. History is never rewritten."
                    >
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();

                                if ((rupeesToPaise(amount) ?? 0) <= 0) {
                                    setRateErrors({
                                        amount_paise: ['Enter a valid amount.'],
                                    });

                                    return;
                                }

                                saveRate.mutate();
                            }}
                        >
                            <FormAlert message={rateProblem} />
                            <MoneyField
                                label="New amount"
                                value={amount}
                                onChange={(event) =>
                                    setAmount(event.target.value)
                                }
                                error={rateErrors.amount_paise?.[0]}
                            />
                            <TextField
                                label="Effective from"
                                type="month"
                                min={currentMonth()}
                                value={effectiveMonth}
                                onChange={(event) =>
                                    setEffectiveMonth(event.target.value)
                                }
                                error={rateErrors.effective_month?.[0]}
                            />
                            <TextAreaField
                                label="Note"
                                optional
                                rows={2}
                                value={note}
                                onChange={(event) =>
                                    setNote(event.target.value)
                                }
                            />
                            <SubmitButton pending={saveRate.isPending}>
                                Save rate
                            </SubmitButton>
                        </form>
                    </Panel>

                    <Panel flush title="Rate history">
                        <Table
                            head={[
                                { label: 'From' },
                                { label: 'Amount', className: 'text-right' },
                            ]}
                        >
                            {(rates.data?.data ?? []).map((rate) => (
                                <tr key={rate.id}>
                                    <Td>
                                        {rate.effective_from.startsWith('2000')
                                            ? 'Default'
                                            : formatMonth(
                                                  rate.effective_from.slice(
                                                      0,
                                                      7,
                                                  ),
                                              )}
                                    </Td>
                                    <Td className="text-right font-semibold text-charcoal tabular">
                                        {formatMoney(rate.amount_paise)}
                                    </Td>
                                </tr>
                            ))}
                        </Table>
                    </Panel>
                </div>
            </div>

            <Panel
                flush
                title="Waivers"
                description="Every waiver, who granted it and why."
            >
                {waivers.data?.data.length === 0 && (
                    <EmptyState title="No waivers granted" />
                )}
                {waivers.data && waivers.data.data.length > 0 && (
                    <Table
                        head={[
                            { label: 'Member' },
                            { label: 'Month' },
                            { label: 'Amount', className: 'text-right' },
                            {
                                label: 'Reason',
                                className: 'hidden md:table-cell',
                            },
                            { label: 'By', className: 'hidden lg:table-cell' },
                            {
                                label: 'When',
                                className: 'hidden lg:table-cell',
                            },
                            { label: '' },
                        ]}
                    >
                        {waivers.data.data.map((waiver) => (
                            <tr key={waiver.id}>
                                <Td className="font-medium text-charcoal">
                                    {waiver.member}
                                </Td>
                                <Td className="whitespace-nowrap">
                                    {formatMonth(waiver.period)}
                                </Td>
                                <Td className="text-right font-semibold text-charcoal tabular">
                                    {formatMoney(waiver.amount_paise)}
                                </Td>
                                <Td className="hidden md:table-cell">
                                    {waiver.reason}
                                </Td>
                                <Td className="hidden lg:table-cell">
                                    {waiver.granted_by}
                                </Td>
                                <Td className="hidden whitespace-nowrap lg:table-cell">
                                    {formatDateTime(waiver.created_at)}
                                </Td>
                                <Td className="text-right">
                                    {waiver.is_reversal && (
                                        <span className="text-xs text-muted-foreground">
                                            Reversal
                                        </span>
                                    )}
                                    {waiver.is_reversed && (
                                        <span className="text-xs text-muted-foreground">
                                            Reversed
                                        </span>
                                    )}
                                    {!waiver.is_reversal &&
                                        !waiver.is_reversed && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setReversing(waiver)
                                                }
                                            >
                                                <RotateCcw /> Reverse
                                            </Button>
                                        )}
                                </Td>
                            </tr>
                        ))}
                    </Table>
                )}
                <Pagination
                    result={waivers.data}
                    page={waiverPage}
                    onPage={setWaiverPage}
                />
            </Panel>

            <ReasonDialog
                open={reversing !== null}
                onOpenChange={(open) => !open && setReversing(null)}
                title="Reverse this waiver"
                description="The amount becomes due again. Both the waiver and this reversal stay on record."
                label="Reason"
                confirmLabel="Reverse waiver"
                onConfirm={async (reason) => {
                    await api.post(
                        `/api/v1/admin/dues/waivers/${reversing?.id}/reverse`,
                        { reason },
                    );
                    void queryClient.invalidateQueries({ queryKey: ['admin'] });
                    toast.success('Waiver reversed.');
                }}
            />
        </div>
    );
}
