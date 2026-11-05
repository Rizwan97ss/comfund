import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Lock, Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    ErrorState,
    PageHeader,
    Panel,
    RowsSkeleton,
} from '@/components/fund/primitives';
import { api } from '@/lib/api';
import { formatMoney } from '@/lib/format';
import { errorMessage } from '@/lib/forms';
import type { FundBalance } from '@/lib/types';
import { cn } from '@/lib/utils';

type Purpose = { id: number; name: string; is_active: boolean };
type FundDetail = FundBalance & { purposes: Purpose[] };

function PurposeEditor({ fund }: { fund: FundDetail }) {
    const queryClient = useQueryClient();
    const [name, setName] = useState('');

    const refresh = () => {
        void queryClient.invalidateQueries({ queryKey: ['admin', 'funds'] });
        void queryClient.invalidateQueries({ queryKey: ['reference'] });
    };

    const add = useMutation({
        mutationFn: () =>
            api.post(`/api/v1/admin/funds/${fund.id}/purposes`, { name }),
        onSuccess: () => {
            setName('');
            refresh();
            toast.success('Purpose added.');
        },
        onError: (error) => toast.error(errorMessage(error)),
    });

    const toggle = useMutation({
        mutationFn: (purpose: Purpose) =>
            api.patch(`/api/v1/admin/fund-purposes/${purpose.id}`, {
                is_active: !purpose.is_active,
            }),
        onSuccess: refresh,
        onError: (error) => toast.error(errorMessage(error)),
    });

    return (
        <div className="grid gap-3">
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                Allowed purposes
            </p>
            <ul className="flex flex-wrap gap-2">
                {fund.purposes.map((purpose) => (
                    <li key={purpose.id}>
                        <button
                            type="button"
                            onClick={() => toggle.mutate(purpose)}
                            disabled={toggle.isPending}
                            title={
                                purpose.is_active
                                    ? 'Click to stop allowing this purpose'
                                    : 'Click to allow this purpose again'
                            }
                            className={cn(
                                'rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                                purpose.is_active
                                    ? 'border-emerald-deep/30 bg-emerald-soft text-emerald-ink hover:bg-emerald-soft/70'
                                    : 'bg-muted text-muted-foreground line-through',
                            )}
                        >
                            {purpose.name}
                        </button>
                    </li>
                ))}
            </ul>
            <form
                className="flex gap-2"
                onSubmit={(event) => {
                    event.preventDefault();

                    if (name.trim()) {
                        add.mutate();
                    }
                }}
            >
                <Input
                    aria-label={`New purpose for ${fund.name}`}
                    placeholder="Add a purpose"
                    value={name}
                    maxLength={120}
                    onChange={(event) => setName(event.target.value)}
                />
                <Button
                    type="submit"
                    variant="outline"
                    disabled={add.isPending}
                >
                    <Plus /> Add
                </Button>
            </form>
        </div>
    );
}

export default function FundsPage() {
    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['admin', 'funds'],
        queryFn: () => api.get<{ data: FundDetail[] }>('/api/v1/admin/funds'),
    });

    return (
        <div className="grid gap-6">
            <PageHeader
                title="Funds"
                description="Each fund is kept apart. Money can never move between funds, and restricted funds can only be spent on the purposes listed below."
            />

            {isLoading && <RowsSkeleton />}
            {error && (
                <ErrorState error={error} onRetry={() => void refetch()} />
            )}

            <div className="grid gap-4 lg:grid-cols-2">
                {(data?.data ?? []).map((fund) => (
                    <Panel key={fund.id}>
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h2 className="flex items-center gap-2 font-semibold text-charcoal">
                                    {fund.name}
                                    {fund.is_restricted && (
                                        <span className="inline-flex items-center gap-1 rounded bg-gold-soft px-1.5 py-0.5 text-[11px] font-semibold text-gold-ink">
                                            <Lock className="size-3" />{' '}
                                            Restricted
                                        </span>
                                    )}
                                </h2>
                                <p className="mt-1 text-2xl font-bold tracking-tight text-charcoal tabular">
                                    {formatMoney(fund.balance_paise)}
                                </p>
                            </div>
                        </div>
                        {fund.is_restricted && (
                            <div className="mt-4 border-t pt-4">
                                <PurposeEditor fund={fund} />
                            </div>
                        )}
                    </Panel>
                ))}
            </div>
        </div>
    );
}
