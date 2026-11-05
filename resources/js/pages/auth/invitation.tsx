import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Check } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { TextAreaField, TextField } from '@/components/fund/form-fields';
import {
    EmptyState,
    FormAlert,
    SubmitButton,
} from '@/components/fund/primitives';
import { ME_KEY } from '@/hooks/use-auth';
import { AuthHeading } from '@/layouts/auth-layout';
import { ApiError, api } from '@/lib/api';
import { errorMessage } from '@/lib/forms';
import type { InvitationState } from '@/lib/types';
import { cn } from '@/lib/utils';

const STEPS = [
    { key: 'verify', label: 'Verify' },
    { key: 'profile', label: 'Profile' },
    { key: 'password', label: 'Password' },
] as const;

function Stepper({ current }: { current: InvitationState['step'] }) {
    const currentIndex = STEPS.findIndex((step) => step.key === current);

    return (
        <ol
            className="mb-6 flex items-center gap-2"
            aria-label="Activation progress"
        >
            {STEPS.map((step, index) => (
                <li
                    key={step.key}
                    className="flex flex-1 items-center gap-2"
                    aria-current={index === currentIndex ? 'step' : undefined}
                >
                    <span
                        className={cn(
                            'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                            index < currentIndex &&
                                'bg-emerald-deep text-white',
                            index === currentIndex && 'bg-gold text-charcoal',
                            index > currentIndex &&
                                'bg-muted text-muted-foreground',
                        )}
                    >
                        {index < currentIndex ? (
                            <Check className="size-4" />
                        ) : (
                            index + 1
                        )}
                    </span>
                    <span
                        className={cn(
                            'text-xs font-medium',
                            index === currentIndex
                                ? 'text-charcoal'
                                : 'text-muted-foreground',
                        )}
                    >
                        {step.label}
                    </span>
                    {index < STEPS.length - 1 && (
                        <span className="h-px flex-1 bg-border" />
                    )}
                </li>
            ))}
        </ol>
    );
}

export default function InvitationPage() {
    const { token } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const url = `/api/v1/invitations/${token}`;

    const invitation = useQuery({
        queryKey: ['invitation', token],
        queryFn: () => api.get<{ data: InvitationState }>(url),
        retry: false,
    });

    const [state, setState] = useState<InvitationState | null>(null);
    const [lastFour, setLastFour] = useState('');
    const [name, setName] = useState('');
    const [phone, setPhone] = useState('');
    const [address, setAddress] = useState('');
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [problem, setProblem] = useState<string | null>(null);
    const [isPending, setIsPending] = useState(false);

    useEffect(() => {
        if (invitation.data) {
            setState(invitation.data.data);
        }
    }, [invitation.data]);

    function applyState(next: InvitationState) {
        setState(next);

        if (next.profile) {
            setName(next.profile.name);
            setPhone(next.profile.phone ?? '');
            setAddress(next.profile.address ?? '');
        }
    }

    async function run(action: () => Promise<void>) {
        setIsPending(true);
        setErrors({});
        setProblem(null);

        try {
            await action();
        } catch (error) {
            if (error instanceof ApiError && error.isValidation) {
                setErrors(error.errors);
            } else {
                setProblem(errorMessage(error));
            }
        } finally {
            setIsPending(false);
        }
    }

    if (invitation.isError || (!invitation.isLoading && !state)) {
        return (
            <EmptyState
                title="This invitation is not valid"
                description="It may have expired, already been used, or been replaced by a newer one. Please ask an administrator to send a new invitation."
                action={
                    <Link
                        to="/login"
                        className="text-sm font-medium text-emerald-deep underline-offset-4 hover:underline"
                    >
                        Go to sign in
                    </Link>
                }
            />
        );
    }

    if (!state) {
        return (
            <p className="text-sm text-muted-foreground">
                Checking your invitation…
            </p>
        );
    }

    return (
        <>
            <Stepper current={state.step} />

            {state.step === 'verify' && (
                <>
                    <AuthHeading
                        title={`Welcome, ${state.first_name}`}
                        description={`This invitation was sent to ${state.email_hint}. To confirm it is you, enter the last four digits of your mobile number.`}
                    />
                    <form
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            void run(async () =>
                                applyState(
                                    (
                                        await api.post<{
                                            data: InvitationState;
                                        }>(`${url}/verify`, {
                                            phone_last_four: lastFour,
                                        })
                                    ).data,
                                ),
                            );
                        }}
                    >
                        <FormAlert message={problem} />
                        <TextField
                            label="Last four digits of your mobile number"
                            inputMode="numeric"
                            maxLength={4}
                            autoComplete="off"
                            autoFocus
                            required
                            value={lastFour}
                            onChange={(event) =>
                                setLastFour(event.target.value)
                            }
                            error={errors.phone_last_four?.[0]}
                        />
                        <SubmitButton pending={isPending} className="w-full">
                            Continue
                        </SubmitButton>
                    </form>
                </>
            )}

            {state.step === 'profile' && (
                <>
                    <AuthHeading
                        title="Confirm your details"
                        description="Correct anything that is out of date."
                    />
                    <form
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            void run(async () =>
                                applyState(
                                    (
                                        await api.post<{
                                            data: InvitationState;
                                        }>(`${url}/profile`, {
                                            name,
                                            phone,
                                            address,
                                        })
                                    ).data,
                                ),
                            );
                        }}
                    >
                        <FormAlert message={problem} />
                        <TextField
                            label="Full name"
                            autoComplete="name"
                            required
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            error={errors.name?.[0]}
                        />
                        <TextField
                            label="Mobile number"
                            type="tel"
                            autoComplete="tel"
                            required
                            value={phone}
                            onChange={(event) => setPhone(event.target.value)}
                            error={errors.phone?.[0]}
                        />
                        <TextAreaField
                            label="Address"
                            autoComplete="street-address"
                            required
                            value={address}
                            onChange={(event) => setAddress(event.target.value)}
                            error={errors.address?.[0]}
                        />
                        <SubmitButton pending={isPending} className="w-full">
                            Continue
                        </SubmitButton>
                    </form>
                </>
            )}

            {state.step === 'password' && (
                <>
                    <AuthHeading
                        title="Set your password"
                        description="Use a long password that you do not use anywhere else."
                    />
                    <form
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            void run(async () => {
                                await api.post(`${url}/activate`, {
                                    password,
                                    password_confirmation: confirmation,
                                });
                                await queryClient.invalidateQueries({
                                    queryKey: ME_KEY,
                                });
                                void navigate('/dashboard', { replace: true });
                            });
                        }}
                    >
                        <FormAlert message={problem} />
                        <TextField
                            label="Password"
                            type="password"
                            autoComplete="new-password"
                            autoFocus
                            required
                            value={password}
                            onChange={(event) =>
                                setPassword(event.target.value)
                            }
                            error={errors.password?.[0]}
                        />
                        <TextField
                            label="Confirm password"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={confirmation}
                            onChange={(event) =>
                                setConfirmation(event.target.value)
                            }
                        />
                        <SubmitButton pending={isPending} className="w-full">
                            Activate my account
                        </SubmitButton>
                    </form>
                </>
            )}
        </>
    );
}
