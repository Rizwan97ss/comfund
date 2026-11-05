import { useQuery } from '@tanstack/react-query';
import {
    AlertTriangle,
    CalendarClock,
    Clock,
    HandCoins,
    Heart,
    Upload,
} from 'lucide-react';
import { Link } from 'react-router';
import { Button } from '@/components/ui/button';
import {
    CardsSkeleton,
    ErrorState,
    PageHeader,
    Panel,
    StatCard,
} from '@/components/fund/primitives';
import { useCurrentUser } from '@/hooks/use-auth';
import { api } from '@/lib/api';
import { formatDate, formatMoney } from '@/lib/format';
import type { MemberDashboard } from '@/lib/types';

export default function MemberDashboardPage() {
    const { data: user } = useCurrentUser();
    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['member', 'dashboard'],
        queryFn: () =>
            api.get<{ data: MemberDashboard }>('/api/v1/member/dashboard'),
    });

    const dashboard = data?.data;
    const firstName = user?.name.split(' ')[0] ?? '';

    return (
        <div className="grid gap-6">
            <PageHeader
                title={`Assalamu alaikum, ${firstName}`}
                description={
                    dashboard
                        ? `Your standing as of ${formatDate(dashboard.as_of)}.`
                        : undefined
                }
                actions={
                    <Button asChild>
                        <Link to="/pay">
                            <Upload /> Submit a payment
                        </Link>
                    </Button>
                }
            />

            {isLoading && <CardsSkeleton />}
            {error && (
                <ErrorState error={error} onRetry={() => void refetch()} />
            )}

            {dashboard && (
                <>
                    {dashboard.rejected_count > 0 && (
                        <Link
                            to="/contributions?status=rejected"
                            className="flex items-center gap-3 rounded-xl border border-destructive/30 bg-danger-soft px-4 py-3 text-sm text-danger-ink hover:underline"
                        >
                            <AlertTriangle className="size-4 shrink-0" />
                            {dashboard.rejected_count} of your payments{' '}
                            {dashboard.rejected_count === 1 ? 'was' : 'were'}{' '}
                            not approved. See why and resubmit.
                        </Link>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            tone="primary"
                            label="Total approved contributions"
                            value={formatMoney(dashboard.total_approved_paise)}
                            icon={<HandCoins />}
                            hint="Confirmed by an administrator"
                        />
                        <StatCard
                            tone={
                                dashboard.dues.outstanding_paise > 0
                                    ? 'attention'
                                    : 'default'
                            }
                            label="Outstanding dues"
                            value={formatMoney(
                                dashboard.dues.outstanding_paise,
                            )}
                            icon={<CalendarClock />}
                            hint={
                                dashboard.dues.advance_paise > 0
                                    ? `${formatMoney(dashboard.dues.advance_paise)} paid in advance`
                                    : `Monthly rate ${formatMoney(dashboard.current_rate_paise)}`
                            }
                        />
                        <StatCard
                            label="Awaiting approval"
                            value={formatMoney(dashboard.pending.amount_paise)}
                            icon={<Clock />}
                            hint={`${dashboard.pending.count} pending ${dashboard.pending.count === 1 ? 'submission' : 'submissions'}. Not yet counted.`}
                        />
                        <StatCard
                            label="Voluntary donations"
                            value={formatMoney(dashboard.voluntary_paise)}
                            icon={<Heart />}
                            hint="Zakat, Sadaqah, Fitrah, Imdad and others"
                        />
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Panel
                            title="Monthly dues"
                            description="How your months stand."
                            actions={
                                <Button asChild variant="link" size="sm">
                                    <Link to="/dues">View all</Link>
                                </Button>
                            }
                        >
                            <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                {[
                                    ['Paid', dashboard.dues.paid_months],
                                    ['Partial', dashboard.dues.partial_months],
                                    ['Unpaid', dashboard.dues.unpaid_months],
                                    ['Waived', dashboard.dues.waived_months],
                                ].map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="rounded-xl bg-muted/50 p-3 text-center"
                                    >
                                        <dd className="text-2xl font-bold text-charcoal tabular">
                                            {value}
                                        </dd>
                                        <dt className="text-xs text-muted-foreground">
                                            {label}
                                        </dt>
                                    </div>
                                ))}
                            </dl>
                        </Panel>

                        <Panel
                            title="By fund"
                            description="Your confirmed giving to each fund."
                            actions={
                                <Button asChild variant="link" size="sm">
                                    <Link to="/statement">Statement</Link>
                                </Button>
                            }
                        >
                            <ul className="divide-y">
                                {dashboard.by_fund.map((fund) => (
                                    <li
                                        key={fund.id}
                                        className="flex items-center justify-between py-2 text-sm first:pt-0 last:pb-0"
                                    >
                                        <span className="text-charcoal">
                                            {fund.name}
                                        </span>
                                        <span className="font-semibold text-charcoal tabular">
                                            {formatMoney(fund.total_paise)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </Panel>
                    </div>
                </>
            )}
        </div>
    );
}
