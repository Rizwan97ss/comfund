import { Lock } from 'lucide-react';
import { formatMoney } from '@/lib/format';

/** A readable list that happens to have bars: exact amounts always stay visible. */
export function BalanceBars({
    rows,
}: {
    rows: {
        id: number;
        name: string;
        balance_paise: number;
        is_restricted?: boolean;
    }[];
}) {
    const largest = Math.max(...rows.map((row) => row.balance_paise), 1);

    return (
        <ul className="grid gap-4">
            {rows.map((row) => (
                <li key={row.id}>
                    <div className="flex items-baseline justify-between gap-3 text-sm">
                        <span className="flex items-center gap-1.5 font-medium text-charcoal">
                            {row.name}
                            {row.is_restricted && (
                                <span className="inline-flex items-center gap-1 rounded bg-gold-soft px-1.5 py-0.5 text-[11px] font-semibold text-gold-ink">
                                    <Lock className="size-3" /> Restricted
                                </span>
                            )}
                        </span>
                        <span className="font-semibold text-charcoal tabular">
                            {formatMoney(row.balance_paise)}
                        </span>
                    </div>
                    <div
                        className="mt-1.5 h-2 overflow-hidden rounded-full bg-muted"
                        aria-hidden="true"
                    >
                        <div
                            className="h-full rounded-full bg-chart-collections transition-[width] duration-500"
                            style={{
                                width: `${(row.balance_paise / largest) * 100}%`,
                            }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}
