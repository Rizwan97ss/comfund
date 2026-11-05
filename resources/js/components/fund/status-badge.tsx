import {
    Ban,
    CheckCircle2,
    CircleDashed,
    Clock,
    MinusCircle,
    RotateCcw,
    XCircle,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Tone = 'good' | 'waiting' | 'bad' | 'neutral';

const toneClasses: Record<Tone, string> = {
    good: 'bg-emerald-soft text-emerald-ink',
    waiting: 'bg-gold-soft text-gold-ink',
    bad: 'bg-danger-soft text-danger-ink',
    neutral: 'bg-muted text-muted-foreground',
};

const statuses: Record<string, { label: string; tone: Tone; icon: ReactNode }> =
    {
        pending: { label: 'Pending', tone: 'waiting', icon: <Clock /> },
        approved: { label: 'Approved', tone: 'good', icon: <CheckCircle2 /> },
        rejected: { label: 'Rejected', tone: 'bad', icon: <XCircle /> },
        reversed: { label: 'Reversed', tone: 'neutral', icon: <RotateCcw /> },
        paid: { label: 'Paid', tone: 'good', icon: <CheckCircle2 /> },
        partial: { label: 'Partial', tone: 'waiting', icon: <CircleDashed /> },
        unpaid: { label: 'Unpaid', tone: 'bad', icon: <XCircle /> },
        waived: { label: 'Waived', tone: 'neutral', icon: <MinusCircle /> },
        active: { label: 'Active', tone: 'good', icon: <CheckCircle2 /> },
        invited: { label: 'Invited', tone: 'waiting', icon: <Clock /> },
        suspended: { label: 'Suspended', tone: 'bad', icon: <Ban /> },
    };

/** Every status carries an icon and a word, so that it never depends on colour alone. */
export function StatusBadge({ status }: { status: string }) {
    const config = statuses[status] ?? {
        label: status,
        tone: 'neutral' as Tone,
        icon: null,
    };

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap [&_svg]:size-3.5',
                toneClasses[config.tone],
            )}
        >
            {config.icon}
            {config.label}
        </span>
    );
}
