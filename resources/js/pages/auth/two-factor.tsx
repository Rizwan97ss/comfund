import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router';
import { TextField } from '@/components/fund/form-fields';
import { FormAlert, SubmitButton } from '@/components/fund/primitives';
import { confirmSignedIn } from '@/hooks/use-auth';
import { AuthHeading } from '@/layouts/auth-layout';
import { api } from '@/lib/api';
import { errorMessage } from '@/lib/forms';

export default function TwoFactorPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const queryClient = useQueryClient();
    const [useRecovery, setUseRecovery] = useState(false);
    const [value, setValue] = useState('');
    const [problem, setProblem] = useState<string | null>(null);
    const [isPending, setIsPending] = useState(false);

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        setIsPending(true);
        setProblem(null);

        try {
            await api.post(
                '/two-factor-challenge',
                useRecovery
                    ? { recovery_code: value.trim() }
                    : { code: value.trim() },
            );
            await confirmSignedIn(queryClient);
            void navigate(
                (location.state as { from?: string } | null)?.from ??
                    '/dashboard',
                { replace: true },
            );
        } catch (error) {
            setProblem(errorMessage(error));
        } finally {
            setIsPending(false);
        }
    }

    return (
        <>
            <AuthHeading
                title="Two-factor verification"
                description={
                    useRecovery
                        ? 'Enter one of your saved recovery codes.'
                        : 'Enter the 6-digit code from your authenticator app.'
                }
            />
            <form onSubmit={submit} className="grid gap-4">
                <FormAlert message={problem} />
                <TextField
                    label={
                        useRecovery ? 'Recovery code' : 'Authentication code'
                    }
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    inputMode={useRecovery ? 'text' : 'numeric'}
                    autoComplete="one-time-code"
                    autoFocus
                    required
                />
                <SubmitButton pending={isPending} className="w-full">
                    Verify
                </SubmitButton>
                <button
                    type="button"
                    className="text-sm font-medium text-emerald-deep underline-offset-4 hover:underline"
                    onClick={() => {
                        setUseRecovery((current) => !current);
                        setValue('');
                    }}
                >
                    {useRecovery
                        ? 'Use an authentication code'
                        : 'Use a recovery code'}
                </button>
            </form>
        </>
    );
}
