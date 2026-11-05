import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useSearchParams } from 'react-router';
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
import type { ReferenceData, Submission } from '@/lib/types';

const schema = z
    .object({
        fund_id: requiredText('Choose a fund.'),
        amount: rupeeAmount,
        applicable_month: z.string(),
        payment_date: requiredText('Choose the payment date.'),
        payment_method: requiredText('Choose how you paid.'),
        transaction_reference: referenceText,
        member_note: z.string().max(500),
        receipt: z.custom<FileList>().optional(),
    })
    .superRefine((values, context) => {
        const isCash = values.payment_method === 'cash';
        const file = values.receipt?.[0];

        if (
            !isCash &&
            values.payment_method !== 'other' &&
            values.transaction_reference === ''
        ) {
            context.addIssue({
                code: 'custom',
                path: ['transaction_reference'],
                message: 'Enter the transaction reference from your payment.',
            });
        }

        if (!isCash && !file) {
            context.addIssue({
                code: 'custom',
                path: ['receipt'],
                message: 'Upload your receipt or a screenshot of the payment.',
            });
        }

        const problem = fileProblem(file);

        if (problem) {
            context.addIssue({
                code: 'custom',
                path: ['receipt'],
                message: problem,
            });
        }
    });

type Values = z.infer<typeof schema>;

export default function SubmitPaymentPage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [searchParams] = useSearchParams();
    const idempotency = useIdempotencyKey();
    const [problem, setProblem] = useState<string | null>(null);

    const reference = useQuery({
        queryKey: ['reference'],
        queryFn: () => api.get<ReferenceData>('/api/v1/reference'),
        staleTime: 300_000,
    });
    const funds = reference.data?.funds ?? [];

    const {
        register,
        handleSubmit,
        watch,
        setError,
        formState: { errors },
    } = useForm<Values>({
        resolver: zodResolver(schema),
        defaultValues: {
            fund_id: searchParams.get('fund') ?? '',
            amount: searchParams.get('amount') ?? '',
            applicable_month: searchParams.get('month') ?? currentMonth(),
            payment_date: todayLocal(),
            payment_method: '',
            transaction_reference: '',
            member_note: '',
        },
    });

    const selectedFund = funds.find(
        (fund) => String(fund.id) === watch('fund_id'),
    );
    const method = watch('payment_method');
    const resubmissionOf = searchParams.get('resubmit');

    const submit = useMutation({
        mutationFn: (values: Values) =>
            api.post<{ data: Submission }>(
                '/api/v1/member/submissions',
                toFormData({
                    fund_id: values.fund_id,
                    amount_paise: rupeesToPaise(values.amount),
                    applicable_month: selectedFund?.is_mandatory
                        ? values.applicable_month
                        : null,
                    payment_date: values.payment_date,
                    payment_method: values.payment_method,
                    transaction_reference: values.transaction_reference,
                    member_note: values.member_note,
                    receipt: values.receipt?.[0],
                    resubmission_of: resubmissionOf,
                }),
                idempotency.current(),
            ),
        onSuccess: (response) => {
            idempotency.renew();
            void queryClient.invalidateQueries({ queryKey: ['member'] });
            toast.success(
                'Payment submitted. It stays pending until an administrator verifies it.',
            );
            void navigate(`/contributions/${response.data.uuid}`);
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
                title={
                    resubmissionOf ? 'Resubmit a payment' : 'Submit a payment'
                }
                description="Tell us about a payment you have already made. Nothing is counted until an administrator verifies it."
            />

            <Panel>
                <form
                    onSubmit={handleSubmit((values) => {
                        setProblem(null);
                        submit.mutate(values);
                    })}
                    className="grid gap-5"
                    noValidate
                >
                    <FormAlert message={problem} />

                    <div className="grid gap-5 sm:grid-cols-2">
                        <SelectField
                            label="Fund"
                            error={errors.fund_id?.message}
                            hint={selectedFund?.description ?? undefined}
                            {...register('fund_id')}
                        >
                            <option value="">Choose a fund…</option>
                            {funds.map((fund) => (
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
                                hint="Extra money pays older months first, then becomes an advance."
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
                            label="Payment method"
                            error={errors.payment_method?.message}
                            {...register('payment_method')}
                        >
                            <option value="">Choose…</option>
                            {(reference.data?.payment_methods ?? []).map(
                                (value) => (
                                    <option key={value} value={value}>
                                        {paymentMethodLabels[value] ?? value}
                                    </option>
                                ),
                            )}
                        </SelectField>
                        <TextField
                            label="Transaction reference"
                            optional={method === 'cash' || method === 'other'}
                            autoComplete="off"
                            error={errors.transaction_reference?.message}
                            {...register('transaction_reference')}
                        />
                    </div>

                    <FileField
                        label="Receipt"
                        optional={method === 'cash'}
                        error={errors.receipt?.message}
                        hint="JPG, PNG, WEBP or PDF, up to 5 MB."
                        {...register('receipt')}
                    />
                    <TextAreaField
                        label="Note for the administrator"
                        optional
                        maxLength={500}
                        error={errors.member_note?.message}
                        {...register('member_note')}
                    />

                    <div className="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p className="flex items-start gap-2 text-xs text-muted-foreground">
                            <ShieldCheck className="size-4 shrink-0 text-emerald-deep" />
                            Once submitted, a payment cannot be edited or
                            deleted, so please check it first.
                        </p>
                        <SubmitButton pending={submit.isPending}>
                            Submit payment
                        </SubmitButton>
                    </div>
                </form>
            </Panel>
        </div>
    );
}
