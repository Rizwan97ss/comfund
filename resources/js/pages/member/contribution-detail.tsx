import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, RotateCcw } from 'lucide-react';
import { Link, useParams } from 'react-router';
import { Button } from '@/components/ui/button';
import { DocumentPreview } from '@/components/fund/document-link';
import {
    DetailList,
    ErrorState,
    PageHeader,
    Panel,
    RowsSkeleton,
} from '@/components/fund/primitives';
import { StatusBadge } from '@/components/fund/status-badge';
import { api } from '@/lib/api';
import {
    formatDate,
    formatDateTime,
    formatMoney,
    formatMonth,
    paiseToRupeesInput,
    paymentMethodLabels,
} from '@/lib/format';
import type { Submission } from '@/lib/types';

export default function ContributionDetailPage() {
    const { uuid } = useParams();
    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['member', 'submission', uuid],
        queryFn: () =>
            api.get<{ data: Submission }>(`/api/v1/member/submissions/${uuid}`),
    });

    const item = data?.data;

    const resubmitLink = item
        ? `/pay?${new URLSearchParams({
              resubmit: item.uuid,
              fund: String(item.fund.id),
              amount: paiseToRupeesInput(item.amount_paise),
              ...(item.applicable_month
                  ? { month: item.applicable_month }
                  : {}),
          }).toString()}`
        : '';

    return (
        <div className="mx-auto grid max-w-3xl gap-6">
            <Button
                asChild
                variant="ghost"
                size="sm"
                className="justify-self-start"
            >
                <Link to="/contributions">
                    <ArrowLeft /> My contributions
                </Link>
            </Button>

            {isLoading && <RowsSkeleton />}
            {error && (
                <ErrorState error={error} onRetry={() => void refetch()} />
            )}

            {item && (
                <>
                    <PageHeader
                        title={`${formatMoney(item.amount_paise)} to ${item.fund.name}`}
                        actions={
                            <StatusBadge
                                status={
                                    item.is_reversed ? 'reversed' : item.status
                                }
                            />
                        }
                    />

                    {item.status === 'pending' && (
                        <p className="rounded-xl bg-gold-soft px-4 py-3 text-sm text-gold-ink">
                            This is waiting for an administrator to verify it.
                            It does not count towards your balance yet.
                        </p>
                    )}

                    {item.status === 'rejected' && (
                        <div className="grid gap-3 rounded-xl border border-destructive/30 bg-danger-soft p-4 text-sm text-danger-ink">
                            <p>
                                <strong>Not approved:</strong>{' '}
                                {item.rejection_reason}
                            </p>
                            <p className="text-xs">
                                Reviewed by {item.reviewer} on{' '}
                                {formatDateTime(item.reviewed_at)}
                            </p>
                            <div>
                                <Button asChild size="sm">
                                    <Link to={resubmitLink}>
                                        <RotateCcw /> Correct and resubmit
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    )}

                    {item.is_reversed && (
                        <p className="rounded-xl bg-muted px-4 py-3 text-sm text-muted-foreground">
                            This approved payment was later reversed by an
                            administrator, so it no longer counts towards your
                            balance.
                        </p>
                    )}

                    <Panel title="Details">
                        <DetailList
                            items={[
                                ['Fund', item.fund.name],
                                [
                                    'Amount',
                                    <span className="tabular" key="a">
                                        {formatMoney(item.amount_paise)}
                                    </span>,
                                ],
                                ...(item.applicable_month
                                    ? [
                                          [
                                              'Month',
                                              formatMonth(
                                                  item.applicable_month,
                                              ),
                                          ] as [string, string],
                                      ]
                                    : []),
                                ['Payment date', formatDate(item.payment_date)],
                                [
                                    'Method',
                                    paymentMethodLabels[item.payment_method],
                                ],
                                [
                                    'Reference',
                                    item.transaction_reference ?? '—',
                                ],
                                [
                                    'Submitted',
                                    formatDateTime(item.submitted_at),
                                ],
                                [
                                    'Recorded by',
                                    item.source === 'admin_manual'
                                        ? 'An administrator, on your behalf'
                                        : 'You',
                                ],
                                ...(item.status === 'approved'
                                    ? [
                                          ['Approved by', item.reviewer] as [
                                              string,
                                              string | null,
                                          ],
                                          [
                                              'Approved on',
                                              formatDateTime(item.reviewed_at),
                                          ] as [string, string],
                                      ]
                                    : []),
                                ...(item.member_note
                                    ? [
                                          ['Your note', item.member_note] as [
                                              string,
                                              string,
                                          ],
                                      ]
                                    : []),
                            ]}
                        />
                    </Panel>

                    {item.document && (
                        <Panel title="Receipt">
                            <DocumentPreview document={item.document} />
                        </Panel>
                    )}
                </>
            )}
        </div>
    );
}
