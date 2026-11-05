import { keepPreviousData, useQuery } from '@tanstack/react-query';
import {
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Loader2,
    ShieldAlert,
    ShieldCheck,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { Button } from '@/components/ui/button';
import { SelectField, TextField } from '@/components/fund/form-fields';
import {
    EmptyState,
    ErrorState,
    PageHeader,
    Pagination,
    Panel,
    RowsSkeleton,
    Table,
    Td,
} from '@/components/fund/primitives';
import { api } from '@/lib/api';
import type { AuditEntry, ChainVerification, Paginated } from '@/lib/types';
import { cn } from '@/lib/utils';

const severityClass: Record<string, string> = {
    info: 'bg-muted text-muted-foreground',
    notice: 'bg-emerald-soft text-emerald-ink',
    warning: 'bg-gold-soft text-gold-ink',
    error: 'bg-danger-soft text-danger-ink',
    critical: 'bg-destructive text-white',
};

function formatTimestamp(utc: string): string {
    return new Date(`${utc.replace(' ', 'T').slice(0, 23)}Z`).toLocaleString(
        'en-IN',
        {
            day: 'numeric',
            month: 'short',
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
        },
    );
}

function describe(entry: AuditEntry): string {
    if (entry.route) {
        return `${entry.http_method ?? ''} /${entry.route.replace(/^\//, '')}`;
    }

    return entry.subject_type
        ? `${entry.subject_type} #${entry.subject_id}`
        : '';
}

export default function AuditPage() {
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('');
    const [severity, setSeverity] = useState('');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [page, setPage] = useState(1);
    const [expanded, setExpanded] = useState<number | null>(null);

    const { data, isLoading, error, refetch } = useQuery({
        queryKey: [
            'admin',
            'audit',
            search,
            category,
            severity,
            from,
            to,
            page,
        ],
        queryFn: () =>
            api.get<Paginated<AuditEntry>>('/api/v1/admin/audit-logs', {
                q: search,
                category,
                severity,
                from,
                to,
                page,
            }),
        placeholderData: keepPreviousData,
    });

    const verification = useQuery({
        queryKey: ['admin', 'audit', 'verify'],
        queryFn: () =>
            api.get<{ data: ChainVerification }>(
                '/api/v1/admin/audit-logs/verify',
            ),
        enabled: false,
        gcTime: 0,
    });

    const result = verification.data?.data;

    return (
        <div className="grid gap-6">
            <PageHeader
                title="Audit log"
                description="A permanent record of everything that happens here, including this page being viewed. It cannot be edited or deleted."
                actions={
                    <Button
                        variant="outline"
                        onClick={() => void verification.refetch()}
                        disabled={verification.isFetching}
                    >
                        {verification.isFetching ? (
                            <Loader2 className="animate-spin" />
                        ) : (
                            <ShieldCheck />
                        )}
                        Verify integrity
                    </Button>
                }
            />

            {result && (
                <div
                    role="status"
                    className={cn(
                        'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm',
                        result.valid
                            ? 'border-emerald-deep/20 bg-emerald-soft text-emerald-ink'
                            : 'border-destructive/30 bg-danger-soft text-danger-ink',
                    )}
                >
                    {result.valid ? (
                        <CheckCircle2 className="mt-0.5 size-5 shrink-0" />
                    ) : (
                        <ShieldAlert className="mt-0.5 size-5 shrink-0" />
                    )}
                    {result.valid ? (
                        <span>
                            The hash chain is intact. All{' '}
                            {result.checked.toLocaleString('en-IN')} entries are
                            unaltered and in sequence.
                        </span>
                    ) : (
                        <span>
                            <strong>
                                Tampering detected at entry {result.broken_at}.
                            </strong>{' '}
                            {result.reason} Treat this as a security incident.
                        </span>
                    )}
                </div>
            )}

            <Panel flush>
                <div className="grid gap-4 px-5 pb-4 sm:grid-cols-2 lg:grid-cols-5">
                    <TextField
                        label="Search"
                        type="search"
                        placeholder="Action, person, IP or route"
                        value={search}
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setPage(1);
                        }}
                    />
                    <SelectField
                        label="Category"
                        value={category}
                        onChange={(event) => {
                            setCategory(event.target.value);
                            setPage(1);
                        }}
                    >
                        <option value="">All</option>
                        <option value="finance">Finance</option>
                        <option value="security">Security</option>
                        <option value="data">Data</option>
                        <option value="access">Access</option>
                        <option value="system">System</option>
                    </SelectField>
                    <SelectField
                        label="Severity"
                        value={severity}
                        onChange={(event) => {
                            setSeverity(event.target.value);
                            setPage(1);
                        }}
                    >
                        <option value="">All</option>
                        <option value="info">Info</option>
                        <option value="notice">Notice</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                        <option value="critical">Critical</option>
                    </SelectField>
                    <TextField
                        label="From"
                        type="date"
                        value={from}
                        onChange={(event) => {
                            setFrom(event.target.value);
                            setPage(1);
                        }}
                    />
                    <TextField
                        label="To"
                        type="date"
                        value={to}
                        onChange={(event) => {
                            setTo(event.target.value);
                            setPage(1);
                        }}
                    />
                </div>

                {isLoading && <RowsSkeleton rows={10} />}
                {error && (
                    <ErrorState error={error} onRetry={() => void refetch()} />
                )}
                {data?.data.length === 0 && (
                    <EmptyState
                        icon={<ShieldCheck />}
                        title="No matching entries"
                    />
                )}

                {data && data.data.length > 0 && (
                    <Table
                        head={[
                            { label: '' },
                            { label: 'When' },
                            { label: 'Who' },
                            { label: 'Action' },
                            {
                                label: 'Detail',
                                className: 'hidden lg:table-cell',
                            },
                            {
                                label: 'Severity',
                                className: 'hidden sm:table-cell',
                            },
                        ]}
                    >
                        {data.data.map((entry) => {
                            const isOpen = expanded === entry.sequence;

                            return (
                                <Fragment key={entry.sequence}>
                                    <tr
                                        tabIndex={0}
                                        aria-expanded={isOpen}
                                        className="cursor-pointer outline-none hover:bg-muted/40 focus-visible:bg-muted/60"
                                        onClick={() =>
                                            setExpanded(
                                                isOpen ? null : entry.sequence,
                                            )
                                        }
                                        onKeyDown={(event) =>
                                            event.key === 'Enter' &&
                                            setExpanded(
                                                isOpen ? null : entry.sequence,
                                            )
                                        }
                                    >
                                        <Td className="w-8 pr-0 text-muted-foreground">
                                            {isOpen ? (
                                                <ChevronDown className="size-4" />
                                            ) : (
                                                <ChevronRight className="size-4" />
                                            )}
                                        </Td>
                                        <Td className="text-xs whitespace-nowrap">
                                            {formatTimestamp(entry.occurred_at)}
                                        </Td>
                                        <Td className="max-w-48 truncate text-xs text-charcoal">
                                            {entry.actor?.replace(
                                                / <.*$/,
                                                '',
                                            ) ?? (
                                                <span className="text-muted-foreground">
                                                    System or guest
                                                </span>
                                            )}
                                        </Td>
                                        <Td className="font-mono text-xs text-charcoal">
                                            {entry.action}
                                        </Td>
                                        <Td className="hidden max-w-72 truncate font-mono text-xs text-muted-foreground lg:table-cell">
                                            {describe(entry)}
                                        </Td>
                                        <Td className="hidden sm:table-cell">
                                            <span
                                                className={cn(
                                                    'rounded-full px-2 py-0.5 text-xs font-semibold capitalize',
                                                    severityClass[
                                                        entry.severity
                                                    ],
                                                )}
                                            >
                                                {entry.severity}
                                            </span>
                                        </Td>
                                    </tr>
                                    {isOpen && (
                                        <tr className="bg-muted/30">
                                            <td
                                                colSpan={6}
                                                className="px-5 py-4"
                                            >
                                                <dl className="grid gap-x-6 gap-y-2 text-xs sm:grid-cols-2 lg:grid-cols-3">
                                                    {[
                                                        [
                                                            'Entry',
                                                            `#${entry.sequence}`,
                                                        ],
                                                        ['Person', entry.actor],
                                                        [
                                                            'Role',
                                                            entry.actor_role,
                                                        ],
                                                        [
                                                            'IP address',
                                                            entry.ip_address,
                                                        ],
                                                        [
                                                            'Status',
                                                            entry.status_code,
                                                        ],
                                                        [
                                                            'Request',
                                                            entry.request_id,
                                                        ],
                                                        [
                                                            'Device',
                                                            entry.user_agent,
                                                        ],
                                                        ['Hash', entry.hash],
                                                    ].map(([label, value]) => (
                                                        <div
                                                            key={String(label)}
                                                            className="min-w-0"
                                                        >
                                                            <dt className="font-semibold text-muted-foreground">
                                                                {label}
                                                            </dt>
                                                            <dd className="break-all text-charcoal">
                                                                {value ?? '—'}
                                                            </dd>
                                                        </div>
                                                    ))}
                                                </dl>
                                                {entry.payload && (
                                                    <pre className="mt-3 max-h-80 overflow-auto rounded-lg bg-charcoal p-3 text-xs text-white">
                                                        {JSON.stringify(
                                                            entry.payload,
                                                            null,
                                                            2,
                                                        )}
                                                    </pre>
                                                )}
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            );
                        })}
                    </Table>
                )}

                <Pagination result={data} page={page} onPage={setPage} />
            </Panel>
        </div>
    );
}
