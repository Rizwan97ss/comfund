import { MailCheck } from 'lucide-react';
import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useCurrentUser, useLogout } from '@/hooks/use-auth';
import { AuthHeading } from '@/layouts/auth-layout';
import { api } from '@/lib/api';
import { errorMessage } from '@/lib/forms';

export default function VerifyEmailPage() {
    const { data: user, isLoading, refetch } = useCurrentUser();
    const logout = useLogout();
    const navigate = useNavigate();
    const [isSending, setIsSending] = useState(false);

    if (!isLoading && !user) {
        return <Navigate to="/login" replace />;
    }

    async function resend() {
        setIsSending(true);

        try {
            await api.post('/email/verification-notification');
            toast.success('A new verification link has been sent.');
        } catch (error) {
            toast.error(errorMessage(error));
        } finally {
            setIsSending(false);
        }
    }

    async function check() {
        const result = await refetch();

        if (result.data?.email_verified) {
            void navigate('/dashboard', { replace: true });
        } else {
            toast.info('Your email is not verified yet.');
        }
    }

    return (
        <>
            <AuthHeading
                title="Verify your email"
                description={`We sent a verification link to ${user?.email ?? 'your email'}.`}
            />
            <div className="grid gap-3">
                <div className="flex items-start gap-3 rounded-lg bg-emerald-soft p-4 text-sm text-emerald-ink">
                    <MailCheck className="mt-0.5 size-5 shrink-0" />
                    <p>
                        Open the link in that email to continue. Financial
                        records stay locked until your address is verified.
                    </p>
                </div>
                <Button onClick={check}>I have verified it</Button>
                <Button variant="outline" onClick={resend} disabled={isSending}>
                    Resend the email
                </Button>
                <Button variant="ghost" onClick={() => logout.mutate()}>
                    Sign out
                </Button>
            </div>
        </>
    );
}
