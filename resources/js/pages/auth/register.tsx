import { zodResolver } from '@hookform/resolvers/zod';
import { Clock } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link } from 'react-router';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { TextAreaField, TextField } from '@/components/fund/form-fields';
import { FormAlert, SubmitButton } from '@/components/fund/primitives';
import { AuthHeading } from '@/layouts/auth-layout';
import { api } from '@/lib/api';
import {
    applyServerErrors,
    errorMessage,
    phoneNumber,
    requiredText,
} from '@/lib/forms';

const schema = z
    .object({
        name: requiredText('Enter your full name.').max(120),
        email: z.string().trim().email('Enter a valid email address.'),
        phone: phoneNumber,
        address: requiredText('Enter your address.').max(500),
        password: z.string().min(1, 'Choose a password.'),
        password_confirmation: z.string(),
    })
    .refine((values) => values.password === values.password_confirmation, {
        message: 'The passwords do not match.',
        path: ['password_confirmation'],
    });

type Values = z.infer<typeof schema>;

export default function RegisterPage() {
    const [problem, setProblem] = useState<string | null>(null);
    const [submitted, setSubmitted] = useState(false);
    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isSubmitting },
    } = useForm<Values>({ resolver: zodResolver(schema) });

    async function onSubmit(values: Values) {
        setProblem(null);

        try {
            await api.post('/api/v1/registrations', values);
            setSubmitted(true);
        } catch (error) {
            if (!applyServerErrors(error, setError)) {
                setProblem(errorMessage(error));
            }
        }
    }

    if (submitted) {
        return (
            <>
                <AuthHeading
                    title="JazakAllah khair"
                    description="We have received your registration."
                />
                <div className="grid gap-3">
                    <div className="flex items-start gap-3 rounded-lg bg-gold-soft p-4 text-sm text-gold-ink">
                        <Clock className="mt-0.5 size-5 shrink-0" />
                        <p>
                            An administrator will review your details. We will
                            email you once your account is approved, and you can
                            then sign in with the password you chose.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link to="/login">Back to sign in</Link>
                    </Button>
                </div>
            </>
        );
    }

    return (
        <>
            <AuthHeading
                title="Create an account"
                description="An administrator will review your details before your account is activated."
            />
            <form
                onSubmit={handleSubmit(onSubmit)}
                className="grid gap-4"
                noValidate
            >
                <FormAlert message={problem} />
                <TextField
                    label="Full name"
                    autoComplete="name"
                    autoFocus
                    error={errors.name?.message}
                    {...register('name')}
                />
                <TextField
                    label="Email"
                    type="email"
                    autoComplete="username"
                    error={errors.email?.message}
                    {...register('email')}
                />
                <TextField
                    label="Mobile number"
                    type="tel"
                    autoComplete="tel"
                    error={errors.phone?.message}
                    {...register('phone')}
                />
                <TextAreaField
                    label="Address"
                    autoComplete="street-address"
                    error={errors.address?.message}
                    {...register('address')}
                />
                <TextField
                    label="Password"
                    type="password"
                    autoComplete="new-password"
                    error={errors.password?.message}
                    {...register('password')}
                />
                <TextField
                    label="Confirm password"
                    type="password"
                    autoComplete="new-password"
                    error={errors.password_confirmation?.message}
                    {...register('password_confirmation')}
                />
                <SubmitButton pending={isSubmitting} className="w-full">
                    Register
                </SubmitButton>
            </form>
            <p className="mt-6 text-center text-sm text-muted-foreground">
                Already have an account?{' '}
                <Link
                    to="/login"
                    className="font-medium text-emerald-deep underline-offset-4 hover:underline"
                >
                    Sign in
                </Link>
            </p>
        </>
    );
}
