import { useId } from 'react';
import type { ComponentProps, ReactNode, Ref } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type FieldProps = {
    label: string;
    error?: string;
    hint?: ReactNode;
    optional?: boolean;
};

function FieldShell({
    id,
    label,
    error,
    hint,
    optional,
    children,
}: FieldProps & { id: string; children: ReactNode }) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id} className="font-medium text-charcoal">
                {label}
                {optional && (
                    <span className="font-normal text-muted-foreground">
                        {' '}
                        (optional)
                    </span>
                )}
            </Label>
            {children}
            {hint && !error && (
                <p id={`${id}-hint`} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            )}
            {error && (
                <p
                    id={`${id}-error`}
                    role="alert"
                    className="text-xs font-medium text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}

function describedBy(
    id: string,
    error?: string,
    hint?: ReactNode,
): string | undefined {
    if (error) {
        return `${id}-error`;
    }

    return hint ? `${id}-hint` : undefined;
}

export function TextField({
    label,
    error,
    hint,
    optional,
    ref,
    ...props
}: FieldProps &
    ComponentProps<typeof Input> & { ref?: Ref<HTMLInputElement> }) {
    const id = useId();

    return (
        <FieldShell
            id={id}
            label={label}
            error={error}
            hint={hint}
            optional={optional}
        >
            <Input
                id={id}
                ref={ref}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(id, error, hint)}
                {...props}
            />
        </FieldShell>
    );
}

export function MoneyField({
    label,
    error,
    hint,
    optional,
    ref,
    ...props
}: FieldProps &
    ComponentProps<typeof Input> & { ref?: Ref<HTMLInputElement> }) {
    const id = useId();

    return (
        <FieldShell
            id={id}
            label={label}
            error={error}
            hint={hint}
            optional={optional}
        >
            <div className="relative">
                <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted-foreground">
                    ₹
                </span>
                <Input
                    id={id}
                    ref={ref}
                    inputMode="decimal"
                    autoComplete="off"
                    placeholder="0.00"
                    className="pl-7 tabular"
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy(id, error, hint)}
                    {...props}
                />
            </div>
        </FieldShell>
    );
}

export function SelectField({
    label,
    error,
    hint,
    optional,
    children,
    ref,
    className,
    ...props
}: FieldProps & ComponentProps<'select'> & { ref?: Ref<HTMLSelectElement> }) {
    const id = useId();

    return (
        <FieldShell
            id={id}
            label={label}
            error={error}
            hint={hint}
            optional={optional}
        >
            <select
                id={id}
                ref={ref}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(id, error, hint)}
                className={cn(
                    'h-9 w-full rounded-md border border-input bg-white px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive',
                    className,
                )}
                {...props}
            >
                {children}
            </select>
        </FieldShell>
    );
}

export function TextAreaField({
    label,
    error,
    hint,
    optional,
    ref,
    className,
    ...props
}: FieldProps &
    ComponentProps<'textarea'> & { ref?: Ref<HTMLTextAreaElement> }) {
    const id = useId();

    return (
        <FieldShell
            id={id}
            label={label}
            error={error}
            hint={hint}
            optional={optional}
        >
            <textarea
                id={id}
                ref={ref}
                rows={3}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(id, error, hint)}
                className={cn(
                    'w-full rounded-md border border-input bg-white px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive',
                    className,
                )}
                {...props}
            />
        </FieldShell>
    );
}

export function FileField({
    label,
    error,
    hint,
    optional,
    ref,
    ...props
}: FieldProps & ComponentProps<'input'> & { ref?: Ref<HTMLInputElement> }) {
    const id = useId();

    return (
        <FieldShell
            id={id}
            label={label}
            error={error}
            hint={hint}
            optional={optional}
        >
            <input
                id={id}
                ref={ref}
                type="file"
                accept=".jpg,.jpeg,.png,.webp,.pdf"
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(id, error, hint)}
                className="block w-full cursor-pointer rounded-md border border-dashed border-input bg-white text-sm text-muted-foreground file:mr-3 file:h-9 file:cursor-pointer file:border-0 file:bg-emerald-soft file:px-3 file:text-sm file:font-medium file:text-emerald-ink aria-invalid:border-destructive"
                {...props}
            />
        </FieldShell>
    );
}
