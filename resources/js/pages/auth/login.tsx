import { zodResolver } from '@hookform/resolvers/zod';
import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useLocation, useNavigate } from 'react-router';
import { z } from 'zod';
import { TextField } from '@/components/fund/form-fields';
import { FormAlert, SubmitButton } from '@/components/fund/primitives';
import { confirmSignedIn } from '@/hooks/use-auth';
import { AuthHeading } from '@/layouts/auth-layout';
import { api } from '@/lib/api';
import { applyServerErrors, errorMessage } from '@/lib/forms';

const schema = z.object({
    email: z.string().trim().email('Enter a valid email address.'),
    password: z.string().min(1, 'Enter your password.'),
});

type Values = z.infer<typeof schema>;

export default function LoginPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const queryClient = useQueryClient();
    const [problem, setProblem] = useState<string | null>(null);
    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isSubmitting },
    } = useForm<Values>({ resolver: zodResolver(schema) });

    async function onSubmit(values: Values) {
        setProblem(null);

        try {
            const response = await api.post<{ two_factor?: boolean }>(
                '/login',
                values,
            );

            if (response?.two_factor) {
                void navigate('/two-factor-challenge', {
                    state: location.state,
                });

                return;
            }

            await confirmSignedIn(queryClient);
            void navigate(
                (location.state as { from?: string } | null)?.from ??
                    '/dashboard',
                { replace: true },
            );
        } catch (error) {
            if (!applyServerErrors(error, setError)) {
                setProblem(errorMessage(error));
            }
        }
    }

    return (
        <>
            <AuthHeading
                title="Assalamu alaikum"
                description="Sign in to your community fund account."
            />
            <form
                onSubmit={handleSubmit(onSubmit)}
                className="grid gap-4"
                noValidate
            >
                <FormAlert message={problem} />
                <TextField
                    label="Email"
                    type="email"
                    autoComplete="username"
                    autoFocus
                    error={errors.email?.message}
                    {...register('email')}
                />
                <TextField
                    label="Password"
                    type="password"
                    autoComplete="current-password"
                    error={errors.password?.message}
                    {...register('password')}
                />
                <div className="-mt-1 text-right">
                    <Link
                        to="/forgot-password"
                        className="text-sm font-medium text-emerald-deep underline-offset-4 hover:underline"
                    >
                        Forgot password?
                    </Link>
                </div>
                <SubmitButton pending={isSubmitting} className="w-full">
                    Sign in
                </SubmitButton>
            </form>
            <p className="mt-6 text-center text-xs text-muted-foreground">
                New members join by invitation from an administrator.
            </p>
        </>
    );
}
