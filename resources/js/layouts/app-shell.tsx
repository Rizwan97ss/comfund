import { useQuery } from '@tanstack/react-query';
import {
    BookOpen,
    CalendarCheck,
    ClipboardCheck,
    FileBarChart,
    HandCoins,
    LayoutDashboard,
    LogOut,
    Menu,
    PiggyBank,
    ReceiptText,
    ScrollText,
    Settings,
    ShieldCheck,
    Upload,
    Users,
    Wallet,
} from 'lucide-react';
import { Suspense, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import { LogoMark } from '@/components/fund/logo';
import { RowsSkeleton } from '@/components/fund/primitives';
import { useLogout } from '@/hooks/use-auth';
import { api } from '@/lib/api';
import type { AdminDashboard, CurrentUser } from '@/lib/types';
import { cn } from '@/lib/utils';

type NavItem = {
    to: string;
    label: string;
    icon: ReactNode;
    end?: boolean;
    badge?: number;
};

const memberNav: NavItem[] = [
    {
        to: '/dashboard',
        label: 'Dashboard',
        icon: <LayoutDashboard />,
        end: true,
    },
    { to: '/pay', label: 'Submit a payment', icon: <Upload /> },
    { to: '/contributions', label: 'My contributions', icon: <HandCoins /> },
    { to: '/dues', label: 'Monthly dues', icon: <CalendarCheck /> },
    { to: '/statement', label: 'My statement', icon: <ScrollText /> },
];

function adminNav(pending: number): { heading: string; items: NavItem[] }[] {
    return [
        {
            heading: 'Overview',
            items: [
                {
                    to: '/dashboard',
                    label: 'Dashboard',
                    icon: <LayoutDashboard />,
                    end: true,
                },
                {
                    to: '/admin/approvals',
                    label: 'Approvals',
                    icon: <ClipboardCheck />,
                    badge: pending,
                },
            ],
        },
        {
            heading: 'Community',
            items: [
                { to: '/admin/members', label: 'Members', icon: <Users /> },
                {
                    to: '/admin/dues',
                    label: 'Monthly dues',
                    icon: <CalendarCheck />,
                },
                {
                    to: '/admin/manual-entry',
                    label: 'Manual entry',
                    icon: <HandCoins />,
                },
            ],
        },
        {
            heading: 'Finance',
            items: [
                { to: '/admin/funds', label: 'Funds', icon: <PiggyBank /> },
                {
                    to: '/admin/expenses',
                    label: 'Expenses',
                    icon: <ReceiptText />,
                },
                {
                    to: '/admin/accounts',
                    label: 'Cash & bank',
                    icon: <Wallet />,
                },
                { to: '/admin/ledger', label: 'Ledger', icon: <BookOpen /> },
                {
                    to: '/admin/reports',
                    label: 'Reports',
                    icon: <FileBarChart />,
                },
            ],
        },
        {
            heading: 'Accountability',
            items: [
                {
                    to: '/admin/audit',
                    label: 'Audit log',
                    icon: <ShieldCheck />,
                },
            ],
        },
    ];
}

function NavEntry({
    item,
    onNavigate,
}: {
    item: NavItem;
    onNavigate?: () => void;
}) {
    return (
        <NavLink
            to={item.to}
            end={item.end}
            onClick={onNavigate}
            className={({ isActive }) =>
                cn(
                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors [&_svg]:size-[18px] [&_svg]:shrink-0',
                    isActive
                        ? 'bg-white/15 text-white'
                        : 'text-white/75 hover:bg-white/10 hover:text-white',
                )
            }
        >
            {item.icon}
            <span className="min-w-0 flex-1 truncate">{item.label}</span>
            {item.badge ? (
                <span className="rounded-full bg-gold px-2 py-0.5 text-xs font-bold text-charcoal tabular">
                    {item.badge}
                </span>
            ) : null}
        </NavLink>
    );
}

function SidebarContent({
    user,
    onNavigate,
}: {
    user: CurrentUser;
    onNavigate?: () => void;
}) {
    const logout = useLogout();
    const isAdmin = user.role === 'admin';

    const { data: dashboard } = useQuery({
        queryKey: ['admin', 'dashboard'],
        queryFn: () =>
            api.get<{ data: AdminDashboard }>('/api/v1/admin/dashboard'),
        enabled: isAdmin,
        staleTime: 30_000,
    });

    const groups = isAdmin
        ? adminNav(dashboard?.data.pending.count ?? 0)
        : [{ heading: '', items: memberNav }];

    return (
        <div className="pattern-girih flex h-full flex-col bg-emerald-deep text-white">
            <div className="flex items-center gap-3 px-5 py-5">
                <LogoMark className="size-9 rounded-lg ring-1 ring-white/20" />
                <div className="min-w-0">
                    <p className="truncate font-semibold">Community Fund</p>
                    <p className="text-xs text-white/65">
                        {isAdmin ? 'Administration' : 'Member portal'}
                    </p>
                </div>
            </div>
            <nav
                aria-label="Main"
                className="flex-1 space-y-5 overflow-y-auto px-3 pb-4"
            >
                {groups.map((group) => (
                    <div key={group.heading || 'main'} className="space-y-1">
                        {group.heading && (
                            <p className="px-3 pb-1 text-[11px] font-semibold tracking-wider text-white/50 uppercase">
                                {group.heading}
                            </p>
                        )}
                        {group.items.map((item) => (
                            <NavEntry
                                key={item.to}
                                item={item}
                                onNavigate={onNavigate}
                            />
                        ))}
                    </div>
                ))}
            </nav>
            <div className="space-y-1 border-t border-white/15 p-3">
                <NavEntry
                    item={{
                        to: '/settings',
                        label: 'Settings',
                        icon: <Settings />,
                    }}
                    onNavigate={onNavigate}
                />
                <button
                    type="button"
                    onClick={() => logout.mutate()}
                    disabled={logout.isPending}
                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white/75 transition-colors hover:bg-white/10 hover:text-white"
                >
                    <LogOut className="size-[18px]" />
                    Sign out
                </button>
                <div className="px-3 pt-2">
                    <p className="truncate text-sm font-medium">{user.name}</p>
                    <p className="truncate text-xs text-white/60">
                        {user.member_code ?? user.email}
                    </p>
                </div>
            </div>
        </div>
    );
}

export function AppShell({ user }: { user: CurrentUser }) {
    const [mobileOpen, setMobileOpen] = useState(false);
    const location = useLocation();

    useEffect(() => {
        // Client-side navigation is invisible to the server, so report it for the audit log.
        api.post('/api/v1/activity/page-view', {
            path: location.pathname,
        }).catch(() => undefined);
    }, [location.pathname]);

    return (
        <div className="min-h-svh lg:grid lg:grid-cols-[17rem_minmax(0,1fr)]">
            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded focus:bg-white focus:px-3 focus:py-2"
            >
                Skip to content
            </a>
            <aside className="sticky top-0 hidden h-svh lg:block">
                <SidebarContent user={user} />
            </aside>

            <div className="flex min-w-0 flex-col">
                <header className="sticky top-0 z-30 flex items-center gap-3 border-b bg-ivory/90 px-4 py-3 backdrop-blur lg:hidden">
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Open navigation"
                        onClick={() => setMobileOpen(true)}
                    >
                        <Menu />
                    </Button>
                    <LogoMark className="size-7" />
                    <span className="font-semibold text-charcoal">
                        Community Fund
                    </span>
                </header>

                <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                    <SheetContent
                        side="left"
                        className="w-72 border-0 p-0 [&>button]:text-white"
                    >
                        <SheetTitle className="sr-only">Navigation</SheetTitle>
                        <SheetDescription className="sr-only">
                            Main navigation
                        </SheetDescription>
                        <SidebarContent
                            user={user}
                            onNavigate={() => setMobileOpen(false)}
                        />
                    </SheetContent>
                </Sheet>

                <main
                    id="main"
                    className="mx-auto w-full max-w-7xl flex-1 p-4 sm:p-6 lg:p-8"
                >
                    <Suspense fallback={<RowsSkeleton rows={8} />}>
                        <div
                            key={location.pathname}
                            className="animate-in duration-300 fade-in slide-in-from-bottom-1"
                        >
                            <Outlet />
                        </div>
                    </Suspense>
                </main>
            </div>
        </div>
    );
}
