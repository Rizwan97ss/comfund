import type { ReactNode } from 'react';
import { Outlet } from 'react-router';
import { LogoMark } from '@/components/fund/logo';

export function AuthLayout() {
    return (
        <div className="grid min-h-svh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <aside className="pattern-girih relative hidden flex-col justify-between overflow-hidden bg-emerald-deep p-12 text-white lg:flex">
                <div className="flex items-center gap-3">
                    <LogoMark className="size-10 rounded-lg ring-1 ring-white/20" />
                    <span className="text-lg font-semibold">
                        Community Fund
                    </span>
                </div>
                <div>
                    <p className="text-3xl leading-snug font-semibold text-balance">
                        Every contribution held in trust, and accounted for.
                    </p>
                    <p className="mt-4 max-w-md text-white/75">
                        Monthly dues, Zakat, Sadaqah, Fitrah and Imdad, each
                        kept separately, with a complete record of every
                        decision.
                    </p>
                </div>
                <p className="text-sm text-white/60">
                    Private to members and administrators of this community.
                </p>
            </aside>
            <main className="pattern-girih-light flex items-center justify-center p-6 sm:p-10">
                <div className="w-full max-w-md">
                    <div className="mb-8 flex items-center gap-3 lg:hidden">
                        <LogoMark />
                        <span className="font-semibold text-charcoal">
                            Community Fund
                        </span>
                    </div>
                    <div className="rounded-2xl border bg-card p-6 shadow-card sm:p-8">
                        <Outlet />
                    </div>
                </div>
            </main>
        </div>
    );
}

export function AuthHeading({
    title,
    description,
}: {
    title: string;
    description?: ReactNode;
}) {
    return (
        <div className="mb-6">
            <h1 className="text-xl font-bold tracking-tight text-charcoal">
                {title}
            </h1>
            {description && (
                <p className="mt-1 text-sm text-muted-foreground">
                    {description}
                </p>
            )}
        </div>
    );
}
