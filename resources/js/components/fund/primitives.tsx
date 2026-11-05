import {
    AlertCircle,
    ChevronLeft,
    ChevronRight,
    Inbox,
    Loader2,
    RefreshCw,
} from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { errorMessage } from '@/lib/forms';
import type { Paginated } from '@/lib/types';
import { cn } from '@/lib/utils';

export function PageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="min-w-0">
                <h1 className="text-2xl font-bold tracking-tight text-balance text-charcoal">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}

export function Panel({
    title,
    description,
    actions,
    children,
    className,
    flush = false,
}: {
    title?: string;
    description?: string;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
    flush?: boolean;
}) {
    return (
        <section
            className={cn('rounded-2xl border bg-card shadow-card', className)}
        >
            {(title || actions) && (
                <div className="flex flex-wrap items-start justify-between gap-3 px-5 pt-5">
                    <div>
                        {title && (
                            <h2 className="font-semibold text-charcoal">
                                {title}
                            </h2>
                        )}
                        {description && (
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {actions}
                </div>
            )}
            <div className={flush ? 'mt-4' : 'p-5'}>{children}</div>
        </section>
    );
}

export function StatCard({
    label,
    value,
    hint,
    icon,
    tone = 'default',
}: {
    label: string;
    value: string;
    hint?: string;
    icon?: ReactNode;
    tone?: 'default' | 'primary' | 'attention';
}) {
    return (
        <div
            className={cn(
                'relative overflow-hidden rounded-2xl border p-5 shadow-card',
                tone === 'primary' &&
                    'pattern-girih border-transparent bg-emerald-deep text-white',
                tone === 'attention' && 'border-gold/50 bg-gold-soft',
                tone === 'default' && 'bg-card',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <p
                    className={cn(
                        'text-sm font-medium',
                        tone === 'primary'
                            ? 'text-white/80'
                            : 'text-muted-foreground',
                    )}
                >
                    {label}
                </p>
                {icon && (
                    <span
                        className={cn(
                            '[&_svg]:size-5',
                            tone === 'primary'
                                ? 'text-gold'
                                : 'text-emerald-deep',
                        )}
                    >
                        {icon}
                    </span>
                )}
            </div>
            <p
                className={cn(
                    'mt-2 text-2xl font-bold tracking-tight tabular',
                    tone !== 'primary' && 'text-charcoal',
                )}
            >
                {value}
            </p>
            {hint && (
                <p
                    className={cn(
                        'mt-1 text-xs',
                        tone === 'primary'
                            ? 'text-white/75'
                            : 'text-muted-foreground',
                    )}
                >
                    {hint}
                </p>
            )}
        </div>
    );
}

export function EmptyState({
    title,
    description,
    action,
    icon,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
    icon?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-12 text-center">
            <span className="mb-3 flex size-12 items-center justify-center rounded-full bg-emerald-soft text-emerald-deep [&_svg]:size-6">
                {icon ?? <Inbox />}
            </span>
            <p className="font-semibold text-charcoal">{title}</p>
            {description && (
                <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                    {description}
                </p>
            )}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}

export function ErrorState({
    error,
    onRetry,
}: {
    error: unknown;
    onRetry?: () => void;
}) {
    return (
        <div
            role="alert"
            className="flex flex-col items-center justify-center px-6 py-12 text-center"
        >
            <span className="mb-3 flex size-12 items-center justify-center rounded-full bg-danger-soft text-danger-ink">
                <AlertCircle className="size-6" />
            </span>
            <p className="font-semibold text-charcoal">
                This could not be loaded
            </p>
            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                {errorMessage(error)}
            </p>
            {onRetry && (
                <Button
                    variant="outline"
                    size="sm"
                    className="mt-4"
                    onClick={onRetry}
                >
                    <RefreshCw /> Try again
                </Button>
            )}
        </div>
    );
}

export function CardsSkeleton({ count = 4 }: { count?: number }) {
    return (
        <div
            className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            aria-busy="true"
            aria-label="Loading"
        >
            {Array.from({ length: count }, (_, index) => (
                <Skeleton key={index} className="h-28 rounded-2xl" />
            ))}
        </div>
    );
}

export function RowsSkeleton({ rows = 6 }: { rows?: number }) {
    return (
        <div
            className="flex flex-col gap-3 p-5"
            aria-busy="true"
            aria-label="Loading"
        >
            {Array.from({ length: rows }, (_, index) => (
                <Skeleton key={index} className="h-9 w-full" />
            ))}
        </div>
    );
}

export function Pagination<T>({
    result,
    page,
    onPage,
}: {
    result: Paginated<T> | undefined;
    page: number;
    onPage: (page: number) => void;
}) {
    const lastPage = result?.meta?.last_page ?? result?.last_page ?? 1;
    const total = result?.meta?.total ?? result?.total;

    if (lastPage <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between border-t px-5 py-3 text-sm text-muted-foreground">
            <span>
                Page {page} of {lastPage}
                {total !== undefined && ` · ${total} records`}
            </span>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={page <= 1}
                    onClick={() => onPage(page - 1)}
                >
                    <ChevronLeft /> Previous
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={page >= lastPage}
                    onClick={() => onPage(page + 1)}
                >
                    Next <ChevronRight />
                </Button>
            </div>
        </div>
    );
}

export function Table({
    head,
    children,
}: {
    head: { label: string; className?: string }[];
    children: ReactNode;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <thead>
                    <tr className="border-y bg-muted/50 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {head.map((column, index) => (
                            <th
                                key={index}
                                scope="col"
                                className={cn(
                                    'px-5 py-2.5 font-semibold whitespace-nowrap',
                                    column.className,
                                )}
                            >
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y">{children}</tbody>
            </table>
        </div>
    );
}

export function Td({ className, ...props }: ComponentProps<'td'>) {
    return (
        <td className={cn('px-5 py-3 align-middle', className)} {...props} />
    );
}

export function SubmitButton({
    pending,
    children,
    ...props
}: ComponentProps<typeof Button> & { pending?: boolean }) {
    return (
        <Button type="submit" disabled={pending || props.disabled} {...props}>
            {pending && <Loader2 className="animate-spin" />}
            {children}
        </Button>
    );
}

export function DetailList({ items }: { items: [string, ReactNode][] }) {
    return (
        <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            {items.map(([label, value]) => (
                <div key={label} className="min-w-0">
                    <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {label}
                    </dt>
                    <dd className="mt-0.5 text-sm break-words text-charcoal">
                        {value ?? '—'}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export function FormAlert({ message }: { message: string | null | undefined }) {
    if (!message) {
        return null;
    }

    return (
        <div
            role="alert"
            className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-danger-soft px-3 py-2.5 text-sm text-danger-ink"
        >
            <AlertCircle className="mt-0.5 size-4 shrink-0" />
            <span>{message}</span>
        </div>
    );
}
