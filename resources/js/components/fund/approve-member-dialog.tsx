import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { MoneyField, TextField } from '@/components/fund/form-fields';
import { FormAlert, SubmitButton } from '@/components/fund/primitives';
import { rupeesToPaise, todayLocal } from '@/lib/format';
import { errorMessage } from '@/lib/forms';

export type ApprovalTerms = {
    joined_on: string;
    monthly_contribution_paise: number | null;
};

export function ApproveMemberDialog({
    open,
    onOpenChange,
    memberName,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    memberName: string;
    onConfirm: (terms: ApprovalTerms) => Promise<unknown>;
}) {
    const [joinedOn, setJoinedOn] = useState(todayLocal());
    const [contribution, setContribution] = useState('');
    const [problem, setProblem] = useState<string | null>(null);
    const [isPending, setIsPending] = useState(false);

    async function submit(event: React.FormEvent) {
        event.preventDefault();

        const paise = contribution.trim() ? rupeesToPaise(contribution) : null;

        if (!joinedOn) {
            setProblem('Choose the joining date.');

            return;
        }

        if (contribution.trim() && !paise) {
            setProblem('Enter a valid amount, like 100 or 250.50.');

            return;
        }

        setIsPending(true);
        setProblem(null);

        try {
            await onConfirm({
                joined_on: joinedOn,
                monthly_contribution_paise: paise,
            });
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
                <form onSubmit={submit} className="grid gap-4" noValidate>
                    <DialogHeader>
                        <DialogTitle>Approve {memberName}?</DialogTitle>
                        <DialogDescription>
                            Check that you know this person. They can sign in
                            once approved, and their dues start from the joining
                            date.
                        </DialogDescription>
                    </DialogHeader>
                    <FormAlert message={problem} />
                    <TextField
                        label="Joining date"
                        type="date"
                        max={todayLocal()}
                        value={joinedOn}
                        onChange={(event) => setJoinedOn(event.target.value)}
                        hint="Dues are generated from this month, so a past date creates arrears."
                    />
                    <MoneyField
                        label="Monthly contribution"
                        optional
                        value={contribution}
                        onChange={(event) =>
                            setContribution(event.target.value)
                        }
                        hint="Leave empty to use the standard rate."
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <SubmitButton pending={isPending}>
                            Approve and activate
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
