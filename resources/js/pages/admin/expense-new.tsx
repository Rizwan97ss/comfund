import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router';
import { toast } from 'sonner';
import { z } from 'zod';
import {
    FileField,
    MoneyField,
    SelectField,
    TextAreaField,
    TextField,
} from '@/components/fund/form-fields';
import {
    FormAlert,
    PageHeader,
    Panel,
    SubmitButton,
} from '@/components/fund/primitives';
import { useIdempotencyKey } from '@/hooks/use-idempotency-key';
import { api, toFormData } from '@/lib/api';
import { paymentMethodLabels, rupeesToPaise, todayLocal } from '@/lib/format';
import {
    applyServerErrors,
    errorMessage,
    fileProblem,
    referenceText,
    requiredText,
    rupeeAmount,
} from '@/lib/forms';
import type { AccountBalance, ReferenceData } from '@/lib/types';

const schema = z
    .object({
        fund_id: requiredText('Choose a fund.'),
        financial_account_id: requiredText(
            'Choose the account it was paid from.',
        ),
        amount: rupeeAmount,
        beneficiary: requiredText('Enter who received the money.'),
        purpose: requiredText('Enter the purpose.'),
        expense_date: requiredText('Choose the date.'),
        payment_method: requiredText('Choose the method.'),
        transaction_reference: referenceText,
        description: z.string().max(2000),
        documents: z.custom<FileList>().optional(),
    })
    .superRefine((values, context) => {
        if (
            !['cash', 'other'].includes(values.payment_method) &&
            values.transaction_reference === ''
        ) {
            context.addIssue({
                code: 'custom',
                path: ['transaction_reference'],
                message: 'Enter the transaction reference.',
            });
        }

        Array.from(values.documents ?? []).forEach((file) => {
            const problem = fileProblem(file);

            if (problem) {
                context.addIssue({
                    code: 'custom',
                    path: ['documents'],
                    message: problem,
                });
            }
        });
    });

type Values = z.infer<typeof schema>;

export default function NewExpensePage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const idempotency = useIdempotencyKey();
    const [problem, setProblem] = useState<string | null>(null);

    const reference = useQuery({
        queryKey: ['reference'],
        queryFn: () => api.get<ReferenceData>('/api/v1/reference'),
        staleTime: 300_000,
    });
    const accounts = useQuery({
        queryKey: ['admin', 'accounts'],
        queryFn: () =>
            api.get<{ data: AccountBalance[] }>('/api/v1/admin/accounts'),
    });

    const {
        register,
        handleSubmit,
        watch,
        setError,
        formState: { errors },
    } = useForm<Values>({
        resolver: zodResolver(schema),
        defaultValues: {
            fund_id: '',
            financial_account_id: '',
            amount: '',
            beneficiary: '',
            purpose: '',
            expense_date: todayLocal(),
            payment_method: '',
            transaction_reference: '',
            description: '',
        },
    });

    const fund = reference.data?.funds.find(
        (item) => String(item.id) === watch('fund_id'),
    );

    const create = useMutation({
        mutationFn: ({ amount, documents, ...values }: Values) =>
            api.post(
                '/api/v1/admin/expenses',
                toFormData({
                    ...values,
                    amount_paise: rupeesToPaise(amount),
                    documents:
                        documents && documents.length > 0 ? documents : null,
                }),
                idempotency.current(),
            ),
        onSuccess: () => {
            idempotency.renew();
            void queryClient.invalidateQueries({ queryKey: ['admin'] });
            toast.success('Expense recorded.');
            void navigate('/admin/expenses');
        },
        onError: (error) => {
            if (
                !applyServerErrors(error, setError, { amount_paise: 'amount' })
            ) {
                setProblem(errorMessage(error));
            }
        },
    });

    return (
        <div className="mx-auto grid max-w-3xl gap-6">
            <PageHeader
                title="Record an expense"
                description="This posts to the ledger immediately. Mistakes are corrected with a reversal, never an edit."
            />

            <Panel>
                <form
                    onSubmit={handleSubmit((values) => {
                        setProblem(null);
                        create.mutate(values);
                    })}
                    className="grid gap-5"
                    noValidate
                >
                    <FormAlert message={problem} />

                    <div className="grid gap-5 sm:grid-cols-2">
                        <SelectField
                            label="Fund"
                            error={errors.fund_id?.message}
                            hint={
                                fund?.is_restricted
                                    ? 'Restricted: only its allowed purposes can be chosen.'
                                    : undefined
                            }
                            {...register('fund_id')}
                        >
                            <option value="">Choose a fund…</option>
                            {(reference.data?.funds ?? []).map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                        </SelectField>
                        <SelectField
                            label="Paid from"
                            error={errors.financial_account_id?.message}
                            {...register('financial_account_id')}
                        >
                            <option value="">Choose an account…</option>
                            {(accounts.data?.data ?? [])
                                .filter((account) => account.is_active)
                                .map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.name}
                                    </option>
                                ))}
                        </SelectField>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <MoneyField
                            label="Amount"
                            error={errors.amount?.message}
                            {...register('amount')}
                        />
                        <TextField
                            label="Expense date"
                            type="date"
                            max={todayLocal()}
                            error={errors.expense_date?.message}
                            {...register('expense_date')}
                        />
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <TextField
                            label="Beneficiary or recipient"
                            autoComplete="off"
                            error={errors.beneficiary?.message}
                            {...register('beneficiary')}
                        />
                        {fund?.is_restricted ? (
                            <SelectField
                                label="Purpose"
                                error={errors.purpose?.message}
                                {...register('purpose')}
                            >
                                <option value="">
                                    Choose an allowed purpose…
                                </option>
                                {fund.purposes.map((purpose) => (
                                    <option
                                        key={purpose.id}
                                        value={purpose.name}
                                    >
                                        {purpose.name}
                                    </option>
                                ))}
                            </SelectField>
                        ) : (
                            <TextField
                                label="Purpose"
                                autoComplete="off"
                                error={errors.purpose?.message}
                                {...register('purpose')}
                            />
                        )}
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <SelectField
                            label="Payment method"
                            error={errors.payment_method?.message}
                            {...register('payment_method')}
                        >
                            <option value="">Choose…</option>
                            {(reference.data?.payment_methods ?? []).map(
                                (method) => (
                                    <option key={method} value={method}>
                                        {paymentMethodLabels[method] ?? method}
                                    </option>
                                ),
                            )}
                        </SelectField>
                        <TextField
                            label="Transaction reference"
                            optional={['cash', 'other'].includes(
                                watch('payment_method'),
                            )}
                            autoComplete="off"
                            error={errors.transaction_reference?.message}
                            {...register('transaction_reference')}
                        />
                    </div>

                    <TextAreaField
                        label="Description"
                        optional
                        error={errors.description?.message}
                        {...register('description')}
                    />
                    <FileField
                        label="Supporting documents"
                        optional
                        multiple
                        error={errors.documents?.message}
                        hint="Up to 5 files. JPG, PNG, WEBP or PDF, 5 MB each."
                        {...register('documents')}
                    />

                    <div className="flex justify-end border-t pt-5">
                        <SubmitButton pending={create.isPending}>
                            Record expense
                        </SubmitButton>
                    </div>
                </form>
            </Panel>
        </div>
    );
}
