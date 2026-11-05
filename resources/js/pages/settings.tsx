import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Laptop, Loader2, ShieldCheck, ShieldOff } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { TextAreaField, TextField } from '@/components/fund/form-fields';
import {
    FormAlert,
    PageHeader,
    Panel,
    SubmitButton,
} from '@/components/fund/primitives';
import { ME_KEY, useCurrentUser } from '@/hooks/use-auth';
import { ApiError, api } from '@/lib/api';
import { formatDateTime } from '@/lib/format';
import { errorMessage } from '@/lib/forms';
import type { CurrentUser, SessionInfo } from '@/lib/types';

function ProfilePanel({ user }: { user: CurrentUser }) {
    const queryClient = useQueryClient();
    const [name, setName] = useState(user.name);
    const [phone, setPhone] = useState(user.phone ?? '');
    const [address, setAddress] = useState(user.address ?? '');
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const save = useMutation({
        mutationFn: () =>
            api.patch('/api/v1/me/profile', {
                name,
                phone: phone || null,
                address: address || null,
            }),
        onSuccess: () => {
            setErrors({});
            void queryClient.invalidateQueries({ queryKey: ME_KEY });
            toast.success('Profile saved.');
        },
        onError: (error) => {
            if (error instanceof ApiError && error.isValidation) {
                setErrors(error.errors);
            } else {
                toast.error(errorMessage(error));
            }
        },
    });

    return (
        <Panel
            title="Profile"
            description={`Signed in as ${user.email}. Ask an administrator to change your email or contribution.`}
        >
            <form
                className="grid gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    save.mutate();
                }}
            >
                <TextField
                    label="Full name"
                    autoComplete="name"
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                    error={errors.name?.[0]}
                />
                <TextField
                    label="Mobile number"
                    type="tel"
                    autoComplete="tel"
                    value={phone}
                    onChange={(event) => setPhone(event.target.value)}
                    error={errors.phone?.[0]}
                />
                <TextAreaField
                    label="Address"
                    autoComplete="street-address"
                    value={address}
                    onChange={(event) => setAddress(event.target.value)}
                    error={errors.address?.[0]}
                />
                <div>
                    <SubmitButton pending={save.isPending}>
                        Save profile
                    </SubmitButton>
                </div>
            </form>
        </Panel>
    );
}

function PasswordPanel() {
    const [current, setCurrent] = useState('');
    const [next, setNext] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const save = useMutation({
        mutationFn: () =>
            api.put('/user/password', {
                current_password: current,
                password: next,
                password_confirmation: confirmation,
            }),
        onSuccess: () => {
            setCurrent('');
            setNext('');
            setConfirmation('');
            setErrors({});
            toast.success(
                'Password changed. Your other devices have been signed out.',
            );
        },
        onError: (error) => {
            if (error instanceof ApiError && error.isValidation) {
                setErrors(error.errors);
            } else {
                toast.error(errorMessage(error));
            }
        },
    });

    return (
        <Panel title="Change password">
            <form
                className="grid gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    save.mutate();
                }}
            >
                <TextField
                    label="Current password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={current}
                    onChange={(event) => setCurrent(event.target.value)}
                    error={errors.current_password?.[0]}
                />
                <TextField
                    label="New password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={next}
                    onChange={(event) => setNext(event.target.value)}
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
                <div>
                    <SubmitButton pending={save.isPending}>
                        Change password
                    </SubmitButton>
                </div>
            </form>
        </Panel>
    );
}

/** Asks for the password again before any 2FA change, as the server requires. */
function PasswordConfirmDialog({
    open,
    onOpenChange,
    onConfirmed,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirmed: () => void;
}) {
    const [password, setPassword] = useState('');
    const [problem, setProblem] = useState<string | null>(null);
    const [isPending, setIsPending] = useState(false);

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        setIsPending(true);
        setProblem(null);

        try {
            await api.post('/user/confirm-password', { password });
            setPassword('');
            onOpenChange(false);
            onConfirmed();
        } catch (error) {
            setProblem(errorMessage(error));
        } finally {
            setIsPending(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Confirm your password</DialogTitle>
                        <DialogDescription>
                            For your security, please confirm it is you.
                        </DialogDescription>
                    </DialogHeader>
                    <FormAlert message={problem} />
                    <TextField
                        label="Password"
                        type="password"
                        autoComplete="current-password"
                        autoFocus
                        required
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                    />
                    <DialogFooter>
                        <SubmitButton pending={isPending}>Confirm</SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function TwoFactorPanel({ user }: { user: CurrentUser }) {
    const queryClient = useQueryClient();
    const [confirming, setConfirming] = useState(false);
    const [pendingAction, setPendingAction] = useState<
        (() => Promise<void>) | null
    >(null);
    const [qrSvg, setQrSvg] = useState<string | null>(null);
    const [secret, setSecret] = useState<string | null>(null);
    const [code, setCode] = useState('');
    const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
    const [problem, setProblem] = useState<string | null>(null);
    const [isPending, setIsPending] = useState(false);

    /** Runs an action; if the server wants the password confirmed first, asks and retries. */
    async function withConfirmation(action: () => Promise<void>) {
        setProblem(null);
        setIsPending(true);

        try {
            await action();
        } catch (error) {
            if (error instanceof ApiError && error.status === 423) {
                setPendingAction(() => action);
                setConfirming(true);
            } else {
                setProblem(errorMessage(error));
            }
        } finally {
            setIsPending(false);
        }
    }

    const enable = async () => {
        await api.post('/user/two-factor-authentication');
        const qr = await api.get<{ svg: string }>('/user/two-factor-qr-code');
        const key = await api.get<{ secretKey: string }>(
            '/user/two-factor-secret-key',
        );
        setQrSvg(`data:image/svg+xml;base64,${btoa(qr.svg)}`);
        setSecret(key.secretKey);
    };

    const showRecoveryCodes = async () => {
        setRecoveryCodes(
            await api.get<string[]>('/user/two-factor-recovery-codes'),
        );
    };

    const disable = async () => {
        await api.delete('/user/two-factor-authentication');
        setRecoveryCodes(null);
        await queryClient.invalidateQueries({ queryKey: ME_KEY });
        toast.success('Two-factor authentication turned off.');
    };

    async function confirm(event: React.FormEvent) {
        event.preventDefault();
        await withConfirmation(async () => {
            await api.post('/user/confirmed-two-factor-authentication', {
                code,
            });
            setQrSvg(null);
            setSecret(null);
            setCode('');
            await showRecoveryCodes();
            await queryClient.invalidateQueries({ queryKey: ME_KEY });
            toast.success('Two-factor authentication is on.');
        });
    }

    return (
        <Panel
            title="Two-factor authentication"
            description="An authenticator app adds a code at sign in, so a stolen password alone is not enough."
        >
            <div className="grid gap-4">
                <FormAlert message={problem} />

                <p className="flex items-center gap-2 text-sm font-medium text-charcoal">
                    {user.two_factor_enabled ? (
                        <ShieldCheck className="size-5 text-emerald-deep" />
                    ) : (
                        <ShieldOff className="size-5 text-muted-foreground" />
                    )}
                    {user.two_factor_enabled ? 'On' : 'Off'}
                </p>

                {!user.two_factor_enabled && !qrSvg && (
                    <div>
                        <Button
                            onClick={() => void withConfirmation(enable)}
                            disabled={isPending}
                        >
                            {isPending && <Loader2 className="animate-spin" />}
                            Turn on
                        </Button>
                    </div>
                )}

                {qrSvg && (
                    <form
                        onSubmit={confirm}
                        className="grid gap-4 rounded-xl border p-4"
                    >
                        <p className="text-sm text-muted-foreground">
                            Scan this with your authenticator app, then enter
                            the code it shows.
                        </p>
                        <img
                            src={qrSvg}
                            alt="Two-factor setup QR code"
                            className="size-44 rounded-lg bg-white p-2"
                        />
                        {secret && (
                            <p className="text-xs text-muted-foreground">
                                Cannot scan it? Enter this key:{' '}
                                <code className="font-mono break-all text-charcoal">
                                    {secret}
                                </code>
                            </p>
                        )}
                        <TextField
                            label="Code from your app"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            required
                            value={code}
                            onChange={(event) => setCode(event.target.value)}
                        />
                        <div>
                            <SubmitButton pending={isPending}>
                                Confirm and turn on
                            </SubmitButton>
                        </div>
                    </form>
                )}

                {recoveryCodes && (
                    <div className="grid gap-2 rounded-xl border border-gold/50 bg-gold-soft p-4">
                        <p className="text-sm font-semibold text-gold-ink">
                            Save these recovery codes somewhere safe. Each works
                            once if you lose your device.
                        </p>
                        <ul className="grid gap-1 font-mono text-xs text-charcoal sm:grid-cols-2">
                            {recoveryCodes.map((recoveryCode) => (
                                <li key={recoveryCode}>{recoveryCode}</li>
                            ))}
                        </ul>
                    </div>
                )}

                {user.two_factor_enabled && (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                void withConfirmation(showRecoveryCodes)
                            }
                            disabled={isPending}
                        >
                            Show recovery codes
                        </Button>
                        <Button
                            variant="outline"
                            className="text-destructive"
                            onClick={() => void withConfirmation(disable)}
                            disabled={isPending}
                        >
                            Turn off
                        </Button>
                    </div>
                )}
            </div>

            <PasswordConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                onConfirmed={() => {
                    if (pendingAction) {
                        void withConfirmation(pendingAction);
                    }
                }}
            />
        </Panel>
    );
}

function SessionsPanel() {
    const queryClient = useQueryClient();
    const { data } = useQuery({
        queryKey: ['sessions'],
        queryFn: () => api.get<{ data: SessionInfo[] }>('/api/v1/me/sessions'),
    });

    const end = useMutation({
        mutationFn: (id: string) => api.delete(`/api/v1/me/sessions/${id}`),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['sessions'] });
            toast.success('That device has been signed out.');
        },
        onError: (error) => toast.error(errorMessage(error)),
    });

    return (
        <Panel
            title="Where you are signed in"
            description="End any session you do not recognise, then change your password."
        >
            <ul className="divide-y">
                {(data?.data ?? []).map((session) => (
                    <li
                        key={session.id}
                        className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div className="flex min-w-0 items-start gap-3">
                            <Laptop className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                            <div className="min-w-0">
                                <p className="truncate text-sm text-charcoal">
                                    {session.user_agent ?? 'Unknown device'}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {session.ip_address} ·{' '}
                                    {formatDateTime(session.last_active_at)}
                                </p>
                            </div>
                        </div>
                        {session.is_current ? (
                            <span className="shrink-0 rounded-full bg-emerald-soft px-2 py-0.5 text-xs font-semibold text-emerald-ink">
                                This device
                            </span>
                        ) : (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => end.mutate(session.id)}
                                disabled={end.isPending}
                            >
                                End
                            </Button>
                        )}
                    </li>
                ))}
            </ul>
        </Panel>
    );
}

export default function SettingsPage() {
    const { data: user } = useCurrentUser();

    if (!user) {
        return null;
    }

    return (
        <div className="mx-auto grid max-w-3xl gap-6">
            <PageHeader
                title="Settings"
                description="Your profile and account security."
            />
            <ProfilePanel user={user} />
            <PasswordPanel />
            <TwoFactorPanel user={user} />
            <SessionsPanel />
        </div>
    );
}
