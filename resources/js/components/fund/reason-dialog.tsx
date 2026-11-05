import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { TextAreaField } from '@/components/fund/form-fields';
import { FormAlert, SubmitButton } from '@/components/fund/primitives';
import { errorMessage } from '@/lib/forms';

/**
 * Every irreversible financial action asks for a written reason, and the server
 * rejects it without one.
 */
export function ReasonDialog({
    open,
    onOpenChange,
    title,
    description,
    label,
    confirmLabel,
    destructive = true,
    onConfirm,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    label: string;
    confirmLabel: string;
    destructive?: boolean;
    onConfirm: (reason: string) => Promise<unknown>;
    children?: ReactNode;
}) {
    const [reason, setReason] = useState('');
    const [problem, setProblem] = useState<string | null>(null);
    const [isPending, setIsPending] = useState(false);

    async function submit(event: React.FormEvent) {
        event.preventDefault();

        if (reason.trim().length < 5) {
            setProblem('Please give a reason of at least 5 characters.');

            return;
        }

        setIsPending(true);
        setProblem(null);

        try {
            await onConfirm(reason.trim());
            setReason('');
            onOpenChange(false);
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
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    {children}
                    <FormAlert message={problem} />
                    <TextAreaField
                        label={label}
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        maxLength={400}
                        autoFocus
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <SubmitButton
                            pending={isPending}
                            variant={destructive ? 'destructive' : 'default'}
                        >
                            {confirmLabel}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
