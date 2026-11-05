import {
    keepPreviousData,
    useMutation,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import {
    ArrowLeft,
    Ban,
    Check,
    MailPlus,
    MinusCircle,
    RotateCcw,
    ScrollText,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { MoneyField } from '@/components/fund/form-fields';
import {
    DetailList,
    EmptyState,
    ErrorState,
    PageHeader,
    Pagination,
    Panel,
    RowsSkeleton,
    StatCard,
    Table,
    Td,
} from '@/components/fund/primitives';
import { ApproveMemberDialog } from '@/components/fund/approve-member-dialog';
import { ReasonDialog } from '@/components/fund/reason-dialog';
import { StatusBadge } from '@/components/fund/status-badge';
import { api } from '@/lib/api';
import {
    formatDate,
    formatMoney,
    formatMonth,
    paiseToRupeesInput,
    rupeesToPaise,
    transactionTypeLabels,
} from '@/lib/format';
import { errorMessage } from '@/lib/forms';
import type {
    DuesMonth,
    DuesSummary,
    LedgerEntry,
    Member,
    Paginated,
} from '@/lib/types';

type Detail = { member: Member; dues: DuesSummary; months: DuesMonth[] };

export default function MemberDetailPage() {
    const { id } = useParams();
    const queryClient = useQueryClient();
    const navigate = useNavigate();
    const url = `/api/v1/admin/members/${id}`;
    const [page, setPage] = useState(1);
    const [waiving, setWaiving] = useState<DuesMonth | null>(null);
    const [waiverAmount, setWaiverAmount] = useState('');
    const [approving, setApproving] = useState(false);
    const [rejecting, setRejecting] = useState(false);

    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['admin', 'member', id],
        queryFn: () => api.get<{ data: Detail }>(url),
    });
    const statement = useQuery({
        queryKey: ['admin', 'member', id, 'statement', page],
        queryFn: () =>
            api.get<Paginated<LedgerEntry>>(`${url}/statement`, { page }),
        placeholderData: keepPreviousData,
    });

    function refresh() {
        void queryClient.invalidateQueries({ queryKey: ['admin'] });
    }

    const action = useMutation({
        mutationFn: (path: string) =>
            api.post<{ message?: string }>(`${url}/${path}`),
        onSuccess: () => {
            refresh();
            toast.success('Done.');
        },
        onError: (mutationError) => toast.error(errorMessage(mutationError)),
    });

    const detail = data?.data;
    const member = detail?.member;

    return (
        <div className="grid gap-6">
            <Button
                asChild
                variant="ghost"
                size="sm"
                className="justify-self-start"
            >
                <Link to="/admin/members">
                    <ArrowLeft /> Members
                </Link>
            </Button>

            {isLoading && <RowsSkeleton />}
            {error && (
                <ErrorState error={error} onRetry={() => void refetch()} />
            )}

            {member && detail && (
                <>
                    <PageHeader
                        title={member.name}
                        description={[member.member_code, member.email]
                            .filter(Boolean)
                            .join(' · ')}
                        actions={
                            <>
                                <StatusBadge status={member.status} />
                                {member.status === 'pending' && (
                                    <>
                                        <Button
                                            size="sm"
                                            onClick={() => setApproving(true)}
                                        >
                                            <Check /> Approve
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="text-destructive"
                                            onClick={() => setRejecting(true)}
                                        >
                                            <X /> Reject
                                        </Button>
                                    </>
                                )}
                                {member.status === 'invited' && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            action.mutate('invitation')
                                        }
                                        disabled={action.isPending}
                                    >
                                        <MailPlus /> Resend invitation
                                    </Button>
                                )}
                                {member.status === 'active' && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="text-destructive"
                                        onClick={() => action.mutate('suspend')}
                                        disabled={action.isPending}
                                    >
                                        <Ban /> Suspend
                                    </Button>
                                )}
                                {member.status === 'suspended' && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            action.mutate('reactivate')
                                        }
                                        disabled={action.isPending}
                                    >
                                        <RotateCcw /> Reactivate
                                    </Button>
                                )}
                            </>
                        }
                    />

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            tone={
                                detail.dues.outstanding_paise > 0
                                    ? 'attention'
                                    : 'default'
                            }
                            label="Outstanding"
                            value={formatMoney(detail.dues.outstanding_paise)}
                            hint={`${detail.dues.unpaid_months} unpaid, ${detail.dues.partial_months} partial`}
                        />
                        <StatCard
                            label="Paid towards dues"
                            value={formatMoney(detail.dues.paid_paise)}
                            hint={`${detail.dues.paid_months} months paid`}
                        />
                        <StatCard
                            label="Advance"
                            value={formatMoney(detail.dues.advance_paise)}
                        />
                        <StatCard
                            label="Waived"
                            value={formatMoney(detail.dues.waived_paise)}
                            hint={`${detail.dues.waived_months} months waived`}
                        />
                    </div>

                    <ApproveMemberDialog
                        open={approving}
                        onOpenChange={setApproving}
                        memberName={member.name}
                        onConfirm={async (terms) => {
                            await api.post(`${url}/approve`, terms);
                            refresh();
                            toast.success(`${member.name} is now active.`);
                        }}
                    />
                    <ReasonDialog
                        open={rejecting}
                        onOpenChange={setRejecting}
                        title={`Reject ${member.name}?`}
                        description="Their account is removed and they are emailed this reason. They can register again."
                        label="Reason"
                        confirmLabel="Reject registration"
                        onConfirm={async (reason) => {
                            await api.post(`${url}/reject`, { reason });
                            // Leave first: this record no longer exists to refetch.
                            await navigate('/admin/members');
                            refresh();
                            toast.success('Registration rejected.');
                        }}
                    />

                    <Panel title="Profile">
                        <DetailList
                            items={[
                                ['Mobile', member.phone],
                                ['Joined', formatDate(member.joined_on)],
                                [
                                    'Monthly contribution',
                                    member.monthly_contribution_paise
                                        ? formatMoney(
                                              member.monthly_contribution_paise,
                                          )
                                        : 'Standard rate',
                                ],
                                ['Activated', formatDate(member.activated_at)],
                                ['Address', member.address ?? '—'],
                            ]}
                        />
                    </Panel>

                    <Panel flush title="Monthly dues">
                        {detail.months.length === 0 ? (
                            <EmptyState title="No dues generated yet" />
                        ) : (
                            <div className="max-h-96 overflow-y-auto">
                                <Table
                                    head={[
                                        { label: 'Month' },
                                        {
                                            label: 'Due',
                                            className: 'text-right',
                                        },
                                        {
                                            label: 'Paid',
                                            className:
                                                'text-right hidden sm:table-cell',
                                        },
                                        {
                                            label: 'Outstanding',
                                            className: 'text-right',
                                        },
                                        { label: 'Status' },
                                        { label: '' },
                                    ]}
                                >
                                    {detail.months.map((month) => (
                                        <tr key={month.id}>
                                            <Td className="font-medium whitespace-nowrap text-charcoal">
                                                {formatMonth(month.period)}
                                            </Td>
                                            <Td className="text-right tabular">
                                                {formatMoney(
                                                    month.amount_paise,
                                                )}
                                            </Td>
                                            <Td className="hidden text-right tabular sm:table-cell">
                                                {formatMoney(month.paid_paise)}
                                            </Td>
                                            <Td className="text-right font-semibold text-charcoal tabular">
                                                {formatMoney(
                                                    month.outstanding_paise,
                                                )}
                                            </Td>
                                            <Td>
                                                <StatusBadge
                                                    status={month.status}
                                                />
                                            </Td>
                                            <Td className="text-right">
                                                {month.outstanding_paise >
                                                    0 && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setWaiverAmount(
                                                                paiseToRupeesInput(
                                                                    month.outstanding_paise,
                                                                ),
                                                            );
                                                            setWaiving(month);
                                                        }}
                                                    >
                                                        <MinusCircle /> Waive
                                                    </Button>
                                                )}
                                            </Td>
                                        </tr>
                                    ))}
                                </Table>
                            </div>
                        )}
                    </Panel>

                    <Panel
                        flush
                        title="Confirmed payments"
                        description="This member's ledger."
                    >
                        {statement.data?.data.length === 0 && (
                            <EmptyState
                                icon={<ScrollText />}
                                title="No confirmed payments yet"
                            />
                        )}
                        {statement.data && statement.data.data.length > 0 && (
                            <Table
                                head={[
                                    { label: 'Date' },
                                    { label: 'Fund' },
                                    {
                                        label: 'Type',
                                        className: 'hidden sm:table-cell',
                                    },
                                    {
                                        label: 'Amount',
                                        className: 'text-right',
                                    },
                                ]}
                            >
                                {statement.data.data.map((entry) => (
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
                        <Pagination
                            result={statement.data}
                            page={page}
                            onPage={setPage}
                        />
                    </Panel>

                    <ReasonDialog
                        open={waiving !== null}
                        onOpenChange={(open) => !open && setWaiving(null)}
                        title={`Waive ${waiving ? formatMonth(waiving.period) : ''} dues`}
                        description="A waiver is recorded with your name and reason. It can be reversed later but never deleted."
                        label="Reason for the waiver"
                        confirmLabel="Grant waiver"
                        destructive={false}
                        onConfirm={async (reason) => {
                            const amount = rupeesToPaise(waiverAmount);

                            if (amount === null || amount <= 0) {
                                throw new Error(
                                    'Enter a valid amount to waive.',
                                );
                            }

                            await api.post(
                                `/api/v1/admin/dues/obligations/${waiving?.id}/waive`,
                                { amount_paise: amount, reason },
                            );
                            refresh();
                            toast.success('Waiver granted.');
                        }}
                    >
                        <MoneyField
                            label="Amount to waive"
                            value={waiverAmount}
                            onChange={(event) =>
                                setWaiverAmount(event.target.value)
                            }
                            hint={
                                waiving
                                    ? `Outstanding: ${formatMoney(waiving.outstanding_paise)}`
                                    : undefined
                            }
                        />
                    </ReasonDialog>
                </>
            )}
        </div>
    );
}
