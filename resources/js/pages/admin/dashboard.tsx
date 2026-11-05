import { useQuery } from '@tanstack/react-query';
import {
    CalendarClock,
    ClipboardCheck,
    HandCoins,
    Heart,
    Landmark,
    ReceiptText,
    Users,
    Wallet,
} from 'lucide-react';
import { Link } from 'react-router';
import { Button } from '@/components/ui/button';
import { BalanceBars } from '@/components/fund/balance-bars';
import {
    CardsSkeleton,
    ErrorState,
    PageHeader,
    Panel,
    StatCard,
} from '@/components/fund/primitives';
import { TrendChart } from '@/components/fund/trend-chart';
import { api } from '@/lib/api';
import { formatDate, formatMoney, formatMonth } from '@/lib/format';
import type { AdminDashboard } from '@/lib/types';

export default function AdminDashboardPage() {
    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['admin', 'dashboard'],
        queryFn: () =>
            api.get<{ data: AdminDashboard }>('/api/v1/admin/dashboard'),
    });

    const dashboard = data?.data;
    const progress =
        dashboard && dashboard.this_month.expected_paise > 0
            ? Math.min(
                  100,
                  Math.round(
                      ((dashboard.this_month.collected_paise +
                          dashboard.this_month.waived_paise) /
                          dashboard.this_month.expected_paise) *
                          100,
                  ),
              )
            : 0;

    return (
        <div className="grid gap-6">
            <PageHeader
                title="Dashboard"
                description={
                    dashboard
                        ? `Confirmed figures as of ${formatDate(dashboard.as_of)}. Pending payments are never included.`
                        : undefined
                }
            />

            {isLoading && <CardsSkeleton count={8} />}
            {error && (
                <ErrorState error={error} onRetry={() => void refetch()} />
            )}

            {dashboard && (
                <>
                    {dashboard.pending.count > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gold/50 bg-gold-soft px-5 py-4">
                            <p className="flex items-center gap-3 text-sm text-gold-ink">
                                <ClipboardCheck className="size-5 shrink-0" />
                                <span>
                                    <strong className="tabular">
                                        {dashboard.pending.count}
                                    </strong>{' '}
                                    {dashboard.pending.count === 1
                                        ? 'payment is'
                                        : 'payments are'}{' '}
                                    waiting for review, worth{' '}
                                    <strong className="tabular">
                                        {formatMoney(
                                            dashboard.pending.amount_paise,
                                        )}
                                    </strong>
                                    .
                                </span>
                            </p>
                            <Button asChild size="sm">
                                <Link to="/admin/approvals">Review now</Link>
                            </Button>
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            tone="primary"
                            label="Available balance"
                            value={formatMoney(
                                dashboard.totals.available_paise,
                            )}
                            icon={<Wallet />}
                            hint="Across all funds and accounts"
                        />
                        <StatCard
                            label="Total collections"
                            value={formatMoney(
                                dashboard.totals.collections_paise,
                            )}
                            icon={<HandCoins />}
                        />
                        <StatCard
                            label="Total expenses"
                            value={formatMoney(dashboard.totals.expenses_paise)}
                            icon={<ReceiptText />}
                        />
                        <StatCard
                            label="Voluntary donations"
                            value={formatMoney(
                                dashboard.totals.donations_paise,
                            )}
                            icon={<Heart />}
                            hint={`Monthly dues: ${formatMoney(dashboard.totals.monthly_dues_paise)}`}
                        />
                    </div>

                    <div className="grid gap-6 xl:grid-cols-3">
                        <Panel
                            title="Collections and expenses"
                            description="Last twelve months"
                            className="xl:col-span-2"
                        >
                            <TrendChart data={dashboard.trend} />
                        </Panel>

                        <Panel
                            title={`${formatMonth(dashboard.this_month.period)} dues`}
                            description={`${progress}% settled`}
                        >
                            <div
                                className="h-2 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                aria-valuenow={progress}
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-label="Share of this month's dues settled"
                            >
                                <div
                                    className="h-full rounded-full bg-chart-collections transition-[width] duration-500"
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                            <dl className="mt-5 grid gap-3 text-sm">
                                {[
                                    [
                                        'Expected',
                                        dashboard.this_month.expected_paise,
                                    ],
                                    [
                                        'Collected',
                                        dashboard.this_month.collected_paise,
                                    ],
                                    [
                                        'Waived',
                                        dashboard.this_month.waived_paise,
                                    ],
                                    [
                                        'Outstanding',
                                        dashboard.this_month.outstanding_paise,
                                    ],
                                ].map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="flex justify-between"
                                    >
                                        <dt className="text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd className="font-semibold text-charcoal tabular">
                                            {formatMoney(Number(value))}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            <div className="mt-5 grid grid-cols-2 gap-3 border-t pt-5">
                                <Link
                                    to="/admin/dues"
                                    className="rounded-xl bg-muted/50 p-3 transition-colors hover:bg-muted"
                                >
                                    <CalendarClock className="mb-1 size-4 text-emerald-deep" />
                                    <p className="text-xl font-bold text-charcoal tabular">
                                        {dashboard.members.with_arrears}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Members in arrears
                                    </p>
                                </Link>
                                <Link
                                    to="/admin/members"
                                    className="rounded-xl bg-muted/50 p-3 transition-colors hover:bg-muted"
                                >
                                    <Users className="mb-1 size-4 text-emerald-deep" />
                                    <p className="text-xl font-bold text-charcoal tabular">
                                        {dashboard.members.active}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Active, {dashboard.members.invited}{' '}
                                        invited
                                    </p>
                                </Link>
                            </div>
                        </Panel>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Panel
                            title="Balance by fund"
                            description="Restricted funds cannot be spent on other purposes."
                        >
                            <BalanceBars rows={dashboard.funds} />
                        </Panel>
                        <Panel
                            title="Where the money is"
                            description="The same total, by physical account."
                            actions={
                                <Landmark className="size-5 text-emerald-deep" />
                            }
                        >
                            <BalanceBars rows={dashboard.accounts} />
                        </Panel>
                    </div>
                </>
            )}
        </div>
    );
}
