import { useState } from 'react';
import { Link } from 'react-router';
import { TextField } from '@/components/fund/form-fields';
import { FormAlert, SubmitButton } from '@/components/fund/primitives';
import { AuthHeading } from '@/layouts/auth-layout';
import { ApiError, api } from '@/lib/api';
import { errorMessage } from '@/lib/forms';

export default function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const [isDone, setIsDone] = useState(false);
    const [isPending, setIsPending] = useState(false);
    const [problem, setProblem] = useState<string | null>(null);

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        setIsPending(true);
        setProblem(null);

        try {
            await api.post('/forgot-password', { email });
            setIsDone(true);
        } catch (error) {
            // The same response for unknown addresses, so this page cannot be used to test who is a member.
            if (error instanceof ApiError && error.status === 422) {
                setIsDone(true);
            } else {
                setProblem(errorMessage(error));
            }
        } finally {
            setIsPending(false);
        }
    }

    return (
        <>
            <AuthHeading
                title="Reset your password"
                description="Enter your email and we will send a reset link."
            />
            {isDone ? (
                <p
                    role="status"
                    className="rounded-lg bg-emerald-soft p-4 text-sm text-emerald-ink"
                >
                    If that address belongs to an account, a reset link is on
                    its way. It expires in 60 minutes.
                </p>
            ) : (
                <form onSubmit={submit} className="grid gap-4">
                    <FormAlert message={problem} />
                    <TextField
                        label="Email"
                        type="email"
                        autoComplete="username"
                        autoFocus
                        required
                        value={email}
                        onChange={(event) => setEmail(event.target.value)}
                    />
                    <SubmitButton pending={isPending} className="w-full">
                        Send reset link
                    </SubmitButton>
                </form>
            )}
            <p className="mt-6 text-center text-sm">
                <Link
                    to="/login"
                    className="font-medium text-emerald-deep underline-offset-4 hover:underline"
                >
                    Back to sign in
                </Link>
            </p>
        </>
    );
}
