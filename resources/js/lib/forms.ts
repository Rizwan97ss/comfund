import type { FieldValues, Path, UseFormSetError } from 'react-hook-form';
import { z } from 'zod';
import { ApiError } from '@/lib/api';
import { rupeesToPaise } from '@/lib/format';

export const rupeeAmount = z
    .string()
    .trim()
    .min(1, 'Enter an amount.')
    .refine((value) => {
        const paise = rupeesToPaise(value);

        return paise !== null && paise > 0;
    }, 'Enter a valid amount, like 100 or 250.50.');

export const optionalRupeeAmount = z
    .string()
    .trim()
    .refine(
        (value) => value === '' || (rupeesToPaise(value) ?? 0) > 0,
        'Enter a valid amount, like 100 or 250.50.',
    );

export const requiredText = (message = 'This field is required.') =>
    z.string().trim().min(1, message);

export const referenceText = z
    .string()
    .trim()
    .max(100)
    .regex(
        /^[A-Za-z0-9\-/_. ]*$/,
        'Use only letters, numbers, spaces and - / _ .',
    );

export const phoneNumber = z
    .string()
    .trim()
    .regex(/^\+?[0-9]{10,15}$/, 'Enter a mobile number of 10 to 15 digits.');

const ALLOWED_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'application/pdf',
];
const MAX_BYTES = 5 * 1024 * 1024;

export function fileProblem(file: File | undefined): string | null {
    if (!file) {
        return null;
    }

    if (!ALLOWED_TYPES.includes(file.type)) {
        return 'Upload a JPG, PNG, WEBP or PDF file.';
    }

    if (file.size > MAX_BYTES) {
        return 'The file must be 5 MB or smaller.';
    }

    return null;
}

/**
 * Put the server's validation messages on the matching fields. The server is the
 * authority: client validation is only there to help people fix mistakes sooner.
 * Returns false when the error could not be attached to a field.
 */
export function applyServerErrors<T extends FieldValues>(
    error: unknown,
    setError: UseFormSetError<T>,
    fieldMap: Record<string, Path<T>> = {},
): boolean {
    if (!(error instanceof ApiError) || !error.isValidation) {
        return false;
    }

    Object.entries(error.errors).forEach(([field, messages]) => {
        setError(fieldMap[field] ?? (field.split('.')[0] as Path<T>), {
            message: messages[0],
        });
    });

    return true;
}

export function errorMessage(error: unknown): string {
    return error instanceof Error
        ? error.message
        : 'Something went wrong. Please try again.';
}
