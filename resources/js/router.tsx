import { Loader2 } from 'lucide-react';
import { lazy, Suspense } from 'react';
import {
    createBrowserRouter,
    Link,
    Navigate,
    Outlet,
    useLocation,
    useRouteError,
} from 'react-router';
import { Button } from '@/components/ui/button';
import { EmptyState, ErrorState } from '@/components/fund/primitives';
import { useCurrentUser } from '@/hooks/use-auth';
import { AppShell } from '@/layouts/app-shell';
import { AuthLayout } from '@/layouts/auth-layout';
import type { CurrentUser } from '@/lib/types';

const LoginPage = lazy(() => import('@/pages/auth/login'));
const RegisterPage = lazy(() => import('@/pages/auth/register'));
const TwoFactorPage = lazy(() => import('@/pages/auth/two-factor'));
const ForgotPasswordPage = lazy(() => import('@/pages/auth/forgot-password'));
const ResetPasswordPage = lazy(() => import('@/pages/auth/reset-password'));
const VerifyEmailPage = lazy(() => import('@/pages/auth/verify-email'));
const InvitationPage = lazy(() => import('@/pages/auth/invitation'));

const MemberDashboardPage = lazy(() => import('@/pages/member/dashboard'));
const SubmitPaymentPage = lazy(() => import('@/pages/member/submit-payment'));
const ContributionsPage = lazy(() => import('@/pages/member/contributions'));
const ContributionDetailPage = lazy(
    () => import('@/pages/member/contribution-detail'),
);
const MemberDuesPage = lazy(() => import('@/pages/member/dues'));
const MemberStatementPage = lazy(() => import('@/pages/member/statement'));

const AdminDashboardPage = lazy(() => import('@/pages/admin/dashboard'));
const ApprovalsPage = lazy(() => import('@/pages/admin/approvals'));
const ApprovalDetailPage = lazy(() => import('@/pages/admin/approval-detail'));
const MembersPage = lazy(() => import('@/pages/admin/members'));
const NewMemberPage = lazy(() => import('@/pages/admin/member-new'));
const MemberDetailPage = lazy(() => import('@/pages/admin/member-detail'));
const ManualEntryPage = lazy(() => import('@/pages/admin/manual-entry'));
const AdminDuesPage = lazy(() => import('@/pages/admin/dues'));
const FundsPage = lazy(() => import('@/pages/admin/funds'));
const ExpensesPage = lazy(() => import('@/pages/admin/expenses'));
const NewExpensePage = lazy(() => import('@/pages/admin/expense-new'));
const AccountsPage = lazy(() => import('@/pages/admin/accounts'));
const LedgerPage = lazy(() => import('@/pages/admin/ledger'));
const ReportsPage = lazy(() => import('@/pages/admin/reports'));
const AuditPage = lazy(() => import('@/pages/admin/audit'));
const SettingsPage = lazy(() => import('@/pages/settings'));

function FullPageSpinner() {
    return (
        <div
            className="flex min-h-svh items-center justify-center"
            role="status"
            aria-label="Loading"
        >
            <Loader2 className="size-8 animate-spin text-emerald-deep" />
        </div>
    );
}

function Lazy() {
    return (
        <Suspense fallback={<FullPageSpinner />}>
            <Outlet />
        </Suspense>
    );
}

/**
 * Decides what to show, for usability only. Every rule here is enforced again by the
 * server on each request, and a tampered client gets nothing from bypassing it.
 */
function RequireUser() {
    const { data: user, isLoading, error, refetch } = useCurrentUser();
    const location = useLocation();

    if (isLoading) {
        return <FullPageSpinner />;
    }

    if (error) {
        return <ErrorState error={error} onRetry={() => void refetch()} />;
    }

    if (!user) {
        return (
            <Navigate to="/login" replace state={{ from: location.pathname }} />
        );
    }

    if (!user.email_verified) {
        return <Navigate to="/email/verify" replace />;
    }

    return <AppShell user={user} />;
}

function RequireRole({ role }: { role: CurrentUser['role'] }) {
    const { data: user } = useCurrentUser();

    if (user?.role !== role) {
        return <Navigate to="/dashboard" replace />;
    }

    return <Outlet />;
}

function GuestOnly() {
    const { data: user, isLoading } = useCurrentUser();

    if (isLoading) {
        return <FullPageSpinner />;
    }

    if (user?.email_verified) {
        return <Navigate to="/dashboard" replace />;
    }

    return <AuthLayout />;
}

function Dashboard() {
    const { data: user } = useCurrentUser();

    return user?.role === 'admin' ? (
        <AdminDashboardPage />
    ) : (
        <MemberDashboardPage />
    );
}

function NotFound() {
    return (
        <EmptyState
            title="Page not found"
            description="This page does not exist, or you do not have access to it."
            action={
                <Button asChild>
                    <Link to="/dashboard">Back to dashboard</Link>
                </Button>
            }
        />
    );
}

function RouteError() {
    const error = useRouteError();

    return (
        <div className="flex min-h-svh items-center justify-center">
            <ErrorState
                error={
                    error instanceof Error
                        ? new Error(
                              'The page failed to load. Please refresh and try again.',
                          )
                        : error
                }
                onRetry={() => window.location.reload()}
            />
        </div>
    );
}

export const router = createBrowserRouter([
    {
        element: <Lazy />,
        errorElement: <RouteError />,
        children: [
            {
                element: <GuestOnly />,
                children: [
                    { path: '/login', element: <LoginPage /> },
                    { path: '/register', element: <RegisterPage /> },
                    {
                        path: '/two-factor-challenge',
                        element: <TwoFactorPage />,
                    },
                    {
                        path: '/forgot-password',
                        element: <ForgotPasswordPage />,
                    },
                    {
                        path: '/reset-password/:token',
                        element: <ResetPasswordPage />,
                    },
                    { path: '/email/verify', element: <VerifyEmailPage /> },
                    { path: '/invitation/:token', element: <InvitationPage /> },
                ],
            },
            {
                element: <RequireUser />,
                children: [
                    {
                        path: '/',
                        element: <Navigate to="/dashboard" replace />,
                    },
                    { path: '/dashboard', element: <Dashboard /> },
                    { path: '/settings', element: <SettingsPage /> },
                    {
                        element: <RequireRole role="member" />,
                        children: [
                            { path: '/pay', element: <SubmitPaymentPage /> },
                            {
                                path: '/contributions',
                                element: <ContributionsPage />,
                            },
                            {
                                path: '/contributions/:uuid',
                                element: <ContributionDetailPage />,
                            },
                            { path: '/dues', element: <MemberDuesPage /> },
                            {
                                path: '/statement',
                                element: <MemberStatementPage />,
                            },
                        ],
                    },
                    {
                        path: '/admin',
                        element: <RequireRole role="admin" />,
                        children: [
                            { path: 'approvals', element: <ApprovalsPage /> },
                            {
                                path: 'approvals/:uuid',
                                element: <ApprovalDetailPage />,
                            },
                            { path: 'members', element: <MembersPage /> },
                            { path: 'members/new', element: <NewMemberPage /> },
                            {
                                path: 'members/:id',
                                element: <MemberDetailPage />,
                            },
                            {
                                path: 'manual-entry',
                                element: <ManualEntryPage />,
                            },
                            { path: 'dues', element: <AdminDuesPage /> },
                            { path: 'funds', element: <FundsPage /> },
                            { path: 'expenses', element: <ExpensesPage /> },
                            {
                                path: 'expenses/new',
                                element: <NewExpensePage />,
                            },
                            { path: 'accounts', element: <AccountsPage /> },
                            { path: 'ledger', element: <LedgerPage /> },
                            { path: 'reports', element: <ReportsPage /> },
                            { path: 'audit', element: <AuditPage /> },
                        ],
                    },
                    { path: '*', element: <NotFound /> },
                ],
            },
        ],
    },
]);
