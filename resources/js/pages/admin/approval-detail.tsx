import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, ArrowLeft, CheckCircle2, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { DocumentPreview } from '@/components/fund/document-link';
import { SelectField } from '@/components/fund/form-fields';
import {
    DetailList,
    EmptyState,
    ErrorState,
    PageHeader,
    Panel,
    RowsSkeleton,
    SubmitButton,
} from '@/components/fund/primitives';
import { ReasonDialog } from '@/components/fund/reason-dialog';
import { StatusBadge } from '@/components/fund/status-badge';
import { api } from '@/lib/api';
import {
    formatDate,
    formatDateTime,
    formatMoney,
    formatMonth,
    paymentMethodLabels,
} from '@/lib/format';
import { errorMessage } from '@/lib/forms';
import type { AccountBalance, Submission } from '@/lib/types';

export default function ApprovalDetailPage() {
    const { uuid } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [accountId, setAccountId] = useState('');
    const [accountError, setAccountError] = useState<string | undefined>();
    const [rejecting, setRejecting] = useState(false);
    const url = `/api/v1/admin/submissions/${uuid}`;

    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['admin', 'submission', uuid],
        queryFn: () => api.get<{ data: Submission }>(url),
    });
    const accounts = useQuery({
        queryKey: ['admin', 'accounts'],
        queryFn: () =>
            api.get<{ data: AccountBalance[] }>('/api/v1/admin/accounts'),
    });

    const item = data?.data;

    function refreshAll() {
        void queryClient.invalidateQueries({ queryKey: ['admin'] });
    }

    const approve = useMutation({
        mutationFn: () =>
            api.post(`${url}/approve`, {
                financial_account_id: Number(accountId),
            }),
        onSuccess: () => {
            refreshAll();
            toast.success('Approved and posted to the ledger.');
            void navigate('/admin/approvals');
        },
        onError: (mutationError) => {
            toast.error(errorMessage(mutationError));
            refreshAll();
        },
    });

    return (
        <div className="grid gap-6">
            <Button
                asChild
                variant="ghost"
                size="sm"
                className="justify-self-start"
            >
                <Link to="/admin/approvals">
                    <ArrowLeft /> Approvals
                </Link>
            </Button>

            {isLoading && <RowsSkeleton />}
            {error && (
                <ErrorState error={error} onRetry={() => void refetch()} />
            )}

            {item && (
                <>
                    <PageHeader
                        title={`${formatMoney(item.amount_paise)} from ${item.member?.name}`}
                        description={`${item.fund.name}${item.applicable_month ? ` for ${formatMonth(item.applicable_month)}` : ''}`}
                        actions={
                            <StatusBadge
                                status={
                                    item.is_reversed ? 'reversed' : item.status
                                }
                            />
                        }
                    />

                    {(item.same_receipt_count ?? 0) > 0 && (
                        <div
                            role="alert"
                            className="flex items-start gap-3 rounded-xl border border-destructive/30 bg-danger-soft px-4 py-3 text-sm text-danger-ink"
                        >
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            <p>
                                This exact receipt file was also attached to{' '}
                                {item.same_receipt_count} other{' '}
                                {item.same_receipt_count === 1
                                    ? 'submission'
                                    : 'submissions'}
                                . Check that it is not being claimed twice.
                            </p>
                        </div>
                    )}

                    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                        <div className="grid content-start gap-6">
                            <Panel title="Payment">
                                <DetailList
                                    items={[
                                        [
                                            'Member',
                                            item.member ? (
                                                <Link
                                                    key="m"
                                                    className="font-medium text-emerald-deep hover:underline"
                                                    to={`/admin/members/${item.member.id}`}
                                                >
                                                    {item.member.name}
                                                </Link>
                                            ) : (
                                                '—'
                                            ),
                                        ],
                                        [
                                            'Member code',
                                            item.member?.member_code,
                                        ],
                                        [
                                            'Payment date',
                                            formatDate(item.payment_date),
                                        ],
                                        [
                                            'Method',
                                            paymentMethodLabels[
                                                item.payment_method
                                            ],
                                        ],
                                        [
                                            'Reference',
                                            item.transaction_reference ?? '—',
                                        ],
                                        [
                                            'Submitted',
                                            formatDateTime(item.submitted_at),
                                        ],
                                        ['Submitted by', item.submitted_by],
                                        [
                                            'Member note',
                                            item.member_note ?? '—',
                                        ],
                                        ...(item.attribution_note
                                            ? [
                                                  [
                                                      'Attribution',
                                                      item.attribution_note,
                                                  ] as [string, string],
                                              ]
                                            : []),
                                    ]}
                                />
                            </Panel>

                            {item.status !== 'pending' && (
                                <Panel title="Decision">
                                    <DetailList
                                        items={[
                                            ['Reviewed by', item.reviewer],
                                            [
                                                'Decided at',
                                                formatDateTime(
                                                    item.reviewed_at,
                                                ),
                                            ],
                                            ...(item.account
                                                ? [
                                                      [
                                                          'Deposited to',
                                                          item.account,
                                                      ] as [string, string],
                                                  ]
                                                : []),
                                            ...(item.rejection_reason
                                                ? [
                                                      [
                                                          'Reason',
                                                          item.rejection_reason,
                                                      ] as [string, string],
                                                  ]
                                                : []),
                                            ...(item.ledger_uuid
                                                ? [
                                                      [
                                                          'Ledger reference',
                                                          <code
                                                              key="l"
                                                              className="text-xs break-all"
                                                          >
                                                              {item.ledger_uuid}
                                                          </code>,
                                                      ] as [
                                                          string,
                                                          React.ReactNode,
                                                      ],
                                                  ]
                                                : []),
                                        ]}
                                    />
                                </Panel>
                            )}

                            {item.status === 'pending' && (
                                <Panel
                                    title="Your decision"
                                    description="This is final. A mistake can only be corrected with a reversal entry."
                                >
                                    <form
                                        className="grid gap-4"
                                        onSubmit={(event) => {
                                            event.preventDefault();

                                            if (!accountId) {
                                                setAccountError(
                                                    'Choose where the money was received.',
                                                );

                                                return;
                                            }

                                            setAccountError(undefined);
                                            approve.mutate();
                                        }}
                                    >
                                        <SelectField
                                            label="Received into"
                                            value={accountId}
                                            onChange={(event) =>
                                                setAccountId(event.target.value)
                                            }
                                            error={accountError}
                                        >
                                            <option value="">
                                                Choose an account…
                                            </option>
                                            {(accounts.data?.data ?? [])
                                                .filter(
                                                    (account) =>
                                                        account.is_active,
                                                )
                                                .map((account) => (
                                                    <option
                                                        key={account.id}
                                                        value={account.id}
                                                    >
                                                        {account.name}
                                                    </option>
                                                ))}
                                        </SelectField>
                                        <div className="flex flex-wrap gap-2">
                                            <SubmitButton
                                                pending={approve.isPending}
                                            >
                                                <CheckCircle2 /> Approve
                                            </SubmitButton>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="text-destructive"
                                                onClick={() =>
                                                    setRejecting(true)
                                                }
                                            >
                                                <XCircle /> Reject
                                            </Button>
                                        </div>
                                    </form>
                                </Panel>
                            )}
                        </div>

                        <Panel
                            title="Receipt"
                            className="content-start self-start"
                        >
                            {item.document ? (
                                <DocumentPreview document={item.document} />
                            ) : (
                                <EmptyState
                                    title="No receipt attached"
                                    description="Cash payments may be submitted without one."
                                />
                            )}
                        </Panel>
                    </div>

                    <ReasonDialog
                        open={rejecting}
                        onOpenChange={setRejecting}
                        title="Reject this payment"
                        description="The member will see this reason and can submit a corrected payment."
                        label="Reason for rejection"
                        confirmLabel="Reject payment"
                        onConfirm={async (reason) => {
                            await api.post(`${url}/reject`, { reason });
                            refreshAll();
                            toast.success('Payment rejected.');
                            void navigate('/admin/approvals');
                        }}
                    />
                </>
            )}
        </div>
    );
}
