import { zodResolver } from '@hookform/resolvers/zod';
import {
    keepPreviousData,
    useMutation,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
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
import {
    currentMonth,
    paymentMethodLabels,
    rupeesToPaise,
    todayLocal,
} from '@/lib/format';
import {
    applyServerErrors,
    errorMessage,
    fileProblem,
    referenceText,
    requiredText,
    rupeeAmount,
} from '@/lib/forms';
import type {
    AccountBalance,
    Member,
    Paginated,
    ReferenceData,
    Submission,
} from '@/lib/types';

const schema = z
    .object({
        member_id: requiredText('Choose the member.'),
        fund_id: requiredText('Choose a fund.'),
        financial_account_id: requiredText(
            'Choose where the money was received.',
        ),
        amount: rupeeAmount,
        applicable_month: z.string(),
        payment_date: requiredText('Choose the payment date.'),
        payment_method: requiredText('Choose the method.'),
        transaction_reference: referenceText,
        attribution_note: z
            .string()
            .trim()
            .min(
                5,
                'Explain who received this and how, in at least 5 characters.',
            ),
        receipt: z.custom<FileList>().optional(),
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

        const problem = fileProblem(values.receipt?.[0]);

        if (problem) {
            context.addIssue({
                code: 'custom',
                path: ['receipt'],
                message: problem,
            });
        }
    });

type Values = z.infer<typeof schema>;

export default function ManualEntryPage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const idempotency = useIdempotencyKey();
    const [problem, setProblem] = useState<string | null>(null);
    const [memberSearch, setMemberSearch] = useState('');

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
    const members = useQuery({
        queryKey: ['admin', 'members', 'picker', memberSearch],
        queryFn: () =>
            api.get<Paginated<Member>>('/api/v1/admin/members', {
                q: memberSearch,
            }),
        placeholderData: keepPreviousData,
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
            member_id: '',
            fund_id: '',
            financial_account_id: '',
            amount: '',
            applicable_month: currentMonth(),
            payment_date: todayLocal(),
            payment_method: '',
            transaction_reference: '',
            attribution_note: '',
        },
    });

    const selectedFund = reference.data?.funds.find(
        (fund) => String(fund.id) === watch('fund_id'),
    );

    const create = useMutation({
        mutationFn: (values: Values) =>
            api.post<{ data: Submission }>(
                '/api/v1/admin/manual-entries',
                toFormData({
                    member_id: values.member_id,
                    fund_id: values.fund_id,
                    financial_account_id: values.financial_account_id,
                    amount_paise: rupeesToPaise(values.amount),
                    applicable_month: selectedFund?.is_mandatory
                        ? values.applicable_month
                        : null,
                    payment_date: values.payment_date,
                    payment_method: values.payment_method,
                    transaction_reference: values.transaction_reference,
                    attribution_note: values.attribution_note,
                    receipt: values.receipt?.[0],
                }),
                idempotency.current(),
            ),
        onSuccess: (response) => {
            idempotency.renew();
            void queryClient.invalidateQueries({ queryKey: ['admin'] });
            toast.success('Entry recorded and posted to the ledger.');
            void navigate(`/admin/approvals/${response.data.uuid}`);
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
                title="Manual entry"
                description="Record money you received on a member's behalf. It is confirmed immediately, with your name attached permanently."
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
                        <TextField
                            label="Find a member"
                            type="search"
                            placeholder="Name or code"
                            value={memberSearch}
                            onChange={(event) =>
                                setMemberSearch(event.target.value)
                            }
                        />
                        <SelectField
                            label="Member"
                            error={errors.member_id?.message}
                            {...register('member_id')}
                        >
                            <option value="">Choose…</option>
                            {(members.data?.data ?? []).map((member) => (
                                <option key={member.id} value={member.id}>
                                    {member.name}{' '}
                                    {member.member_code
                                        ? `(${member.member_code})`
                                        : ''}
                                </option>
                            ))}
                        </SelectField>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <SelectField
                            label="Fund"
                            error={errors.fund_id?.message}
                            {...register('fund_id')}
                        >
                            <option value="">Choose a fund…</option>
                            {(reference.data?.funds ?? []).map((fund) => (
                                <option key={fund.id} value={fund.id}>
                                    {fund.name}
                                </option>
                            ))}
                        </SelectField>
                        <MoneyField
                            label="Amount"
                            error={errors.amount?.message}
                            {...register('amount')}
                        />
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        {selectedFund?.is_mandatory && (
                            <TextField
                                label="Month this pays for"
                                type="month"
                                error={errors.applicable_month?.message}
                                {...register('applicable_month')}
                            />
                        )}
                        <TextField
                            label="Payment date"
                            type="date"
                            max={todayLocal()}
                            error={errors.payment_date?.message}
                            {...register('payment_date')}
                        />
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <SelectField
                            label="Received into"
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
                    </div>

                    <TextField
                        label="Transaction reference"
                        optional={['cash', 'other'].includes(
                            watch('payment_method'),
                        )}
                        autoComplete="off"
                        error={errors.transaction_reference?.message}
                        {...register('transaction_reference')}
                    />
                    <TextAreaField
                        label="Attribution"
                        error={errors.attribution_note?.message}
                        hint="Who handed this over, and when. This stays on the record."
                        {...register('attribution_note')}
                    />
                    <FileField
                        label="Receipt"
                        optional
                        error={errors.receipt?.message}
                        {...register('receipt')}
                    />

                    <div className="flex justify-end border-t pt-5">
                        <SubmitButton pending={create.isPending}>
                            Record entry
                        </SubmitButton>
                    </div>
                </form>
            </Panel>
        </div>
    );
}
