import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router';
import { toast } from 'sonner';
import { z } from 'zod';
import {
    MoneyField,
    TextAreaField,
    TextField,
} from '@/components/fund/form-fields';
import {
    FormAlert,
    PageHeader,
    Panel,
    SubmitButton,
} from '@/components/fund/primitives';
import { api } from '@/lib/api';
import { rupeesToPaise, todayLocal } from '@/lib/format';
import {
    applyServerErrors,
    errorMessage,
    optionalRupeeAmount,
    phoneNumber,
    requiredText,
} from '@/lib/forms';
import type { Member } from '@/lib/types';

const schema = z.object({
    name: requiredText("Enter the member's name."),
    email: z.string().trim().email('Enter a valid email address.'),
    phone: phoneNumber,
    address: z.string().max(500),
    joined_on: requiredText('Choose the joining date.'),
    monthly_contribution: optionalRupeeAmount,
});

type Values = z.infer<typeof schema>;

export default function NewMemberPage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [problem, setProblem] = useState<string | null>(null);

    const {
        register,
        handleSubmit,
        setError,
        formState: { errors },
    } = useForm<Values>({
        resolver: zodResolver(schema),
        defaultValues: {
            name: '',
            email: '',
            phone: '',
            address: '',
            joined_on: todayLocal(),
            monthly_contribution: '',
        },
    });

    const create = useMutation({
        mutationFn: ({ monthly_contribution, ...values }: Values) =>
            api.post<{ data: Member }>('/api/v1/admin/members', {
                ...values,
                monthly_contribution_paise: monthly_contribution
                    ? rupeesToPaise(monthly_contribution)
                    : null,
            }),
        onSuccess: (response) => {
            void queryClient.invalidateQueries({ queryKey: ['admin'] });
            toast.success(
                'Member registered. An invitation has been sent to their email.',
            );
            void navigate(`/admin/members/${response.data.id}`);
        },
        onError: (error) => {
            if (
                !applyServerErrors(error, setError, {
                    monthly_contribution_paise: 'monthly_contribution',
                })
            ) {
                setProblem(errorMessage(error));
            }
        },
    });

    return (
        <div className="mx-auto grid max-w-2xl gap-6">
            <PageHeader
                title="Register a member"
                description="They will receive an invitation to verify their account and set a password."
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
                    <TextField
                        label="Full name"
                        autoComplete="off"
                        error={errors.name?.message}
                        {...register('name')}
                    />
                    <div className="grid gap-5 sm:grid-cols-2">
                        <TextField
                            label="Email"
                            type="email"
                            autoComplete="off"
                            error={errors.email?.message}
                            {...register('email')}
                        />
                        <TextField
                            label="Mobile number"
                            type="tel"
                            autoComplete="off"
                            error={errors.phone?.message}
                            hint="Its last four digits confirm their identity."
                            {...register('phone')}
                        />
                    </div>
                    <TextAreaField
                        label="Address"
                        optional
                        error={errors.address?.message}
                        {...register('address')}
                    />
                    <div className="grid gap-5 sm:grid-cols-2">
                        <TextField
                            label="Joining date"
                            type="date"
                            max={todayLocal()}
                            error={errors.joined_on?.message}
                            hint="Dues are generated from this month, so a past date creates arrears."
                            {...register('joined_on')}
                        />
                        <MoneyField
                            label="Monthly contribution"
                            optional
                            error={errors.monthly_contribution?.message}
                            hint="Leave empty to use the standard rate."
                            {...register('monthly_contribution')}
                        />
                    </div>
                    <div className="flex justify-end border-t pt-5">
                        <SubmitButton pending={create.isPending}>
                            Register and send invitation
                        </SubmitButton>
                    </div>
                </form>
            </Panel>
        </div>
    );
}
