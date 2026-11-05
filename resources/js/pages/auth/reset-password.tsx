import { useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router';
import { TextField } from '@/components/fund/form-fields';
import { FormAlert, SubmitButton } from '@/components/fund/primitives';
import { AuthHeading } from '@/layouts/auth-layout';
import { ApiError, api } from '@/lib/api';
import { errorMessage } from '@/lib/forms';

export default function ResetPasswordPage() {
    const { token } = useParams();
    const [searchParams] = useSearchParams();
    const email = searchParams.get('email') ?? '';
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [problem, setProblem] = useState<string | null>(null);
    const [isDone, setIsDone] = useState(false);
    const [isPending, setIsPending] = useState(false);

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        setIsPending(true);
        setProblem(null);
        setErrors({});

        try {
            await api.post('/reset-password', {
                token,
                email,
                password,
                password_confirmation: confirmation,
            });
            setIsDone(true);
        } catch (error) {
            if (error instanceof ApiError && error.isValidation) {
                setErrors(error.errors);

                if (error.errors.email) {
                    setProblem(error.errors.email[0]);
                }
            } else {
                setProblem(errorMessage(error));
            }
        } finally {
            setIsPending(false);
        }
    }

    if (isDone) {
        return (
            <>
                <AuthHeading
                    title="Password changed"
                    description="You can now sign in with your new password."
                />
                <Link
                    to="/login"
                    className="font-medium text-emerald-deep underline-offset-4 hover:underline"
                >
                    Go to sign in
                </Link>
            </>
        );
    }

    return (
        <>
            <AuthHeading title="Choose a new password" description={email} />
            <form onSubmit={submit} className="grid gap-4">
                <FormAlert message={problem} />
                <TextField
                    label="New password"
                    type="password"
                    autoComplete="new-password"
                    autoFocus
                    required
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    error={errors.password?.[0]}
                />
                <TextField
                    label="Confirm new password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={confirmation}
                    onChange={(event) => setConfirmation(event.target.value)}
                />
                <SubmitButton pending={isPending} className="w-full">
                    Change password
                </SubmitButton>
            </form>
        </>
    );
}
