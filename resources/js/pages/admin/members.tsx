import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { UserPlus, Users } from 'lucide-react';
import { useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SelectField } from '@/components/fund/form-fields';
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
import { StatusBadge } from '@/components/fund/status-badge';
import { api } from '@/lib/api';
import { formatDate } from '@/lib/format';
import type { Member, Paginated } from '@/lib/types';

export default function MembersPage() {
    const navigate = useNavigate();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);

    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['admin', 'members', search, status, page],
        queryFn: () =>
            api.get<Paginated<Member>>('/api/v1/admin/members', {
                q: search,
                status,
                page,
            }),
        placeholderData: keepPreviousData,
    });

    return (
        <div className="grid gap-6">
            <PageHeader
                title="Members"
                description="Everyone registered with the community fund."
                actions={
                    <Button asChild>
                        <Link to="/admin/members/new">
                            <UserPlus /> Register a member
                        </Link>
                    </Button>
                }
            />

            <Panel flush>
                <div className="grid gap-4 px-5 pb-4 sm:grid-cols-[minmax(0,1fr)_12rem]">
                    <div className="grid gap-1.5">
                        <label
                            htmlFor="member-search"
                            className="text-sm font-medium text-charcoal"
                        >
                            Search
                        </label>
                        <Input
                            id="member-search"
                            type="search"
                            placeholder="Name, email, mobile or code"
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                setPage(1);
                            }}
                        />
                    </div>
                    <SelectField
                        label="Status"
                        value={status}
                        onChange={(event) => {
                            setStatus(event.target.value);
                            setPage(1);
                        }}
                    >
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="invited">Invited</option>
                        <option value="suspended">Suspended</option>
                    </SelectField>
                </div>

                {isLoading && <RowsSkeleton />}
                {error && (
                    <ErrorState error={error} onRetry={() => void refetch()} />
                )}
                {data?.data.length === 0 && (
                    <EmptyState
                        icon={<Users />}
                        title="No members found"
                        description={
                            search || status
                                ? 'Try a different search.'
                                : 'Register your first member to get started.'
                        }
                    />
                )}

                {data && data.data.length > 0 && (
                    <Table
                        head={[
                            { label: 'Member' },
                            {
                                label: 'Code',
                                className: 'hidden md:table-cell',
                            },
                            {
                                label: 'Mobile',
                                className: 'hidden lg:table-cell',
                            },
                            {
                                label: 'Joined',
                                className: 'hidden md:table-cell',
                            },
                            { label: 'Status' },
                        ]}
                    >
                        {data.data.map((member) => (
                            <tr
                                key={member.id}
                                tabIndex={0}
                                className="cursor-pointer outline-none hover:bg-muted/40 focus-visible:bg-muted/60"
                                onClick={() =>
                                    void navigate(`/admin/members/${member.id}`)
                                }
                                onKeyDown={(event) =>
                                    event.key === 'Enter' &&
                                    void navigate(`/admin/members/${member.id}`)
                                }
                            >
                                <Td>
                                    <p className="font-medium text-charcoal">
                                        {member.name}
                                    </p>
                                    <p className="text-xs break-all text-muted-foreground">
                                        {member.email}
                                    </p>
                                </Td>
                                <Td className="hidden tabular md:table-cell">
                                    {member.member_code}
                                </Td>
                                <Td className="hidden tabular lg:table-cell">
                                    {member.phone}
                                </Td>
                                <Td className="hidden whitespace-nowrap md:table-cell">
                                    {formatDate(member.joined_on)}
                                </Td>
                                <Td>
                                    <StatusBadge status={member.status} />
                                </Td>
                            </tr>
                        ))}
                    </Table>
                )}

                <Pagination result={data} page={page} onPage={setPage} />
            </Panel>
        </div>
    );
}
