const inr = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
});
const inrWhole = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 0,
});
const inrCompact = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    notation: 'compact',
    maximumFractionDigits: 1,
});

/** All money is integer paise on the wire; it only becomes rupees at the display edge. */
export function formatMoney(paise: number): string {
    return inr.format(paise / 100);
}

export function formatMoneyWhole(paise: number): string {
    return inrWhole.format(paise / 100);
}

export function formatMoneyCompact(paise: number): string {
    return inrCompact.format(paise / 100);
}

/**
 * Parse a rupee amount typed by a person into exact paise, without floating point.
 * Returns null if the text is not a valid amount with at most two decimals.
 */
export function rupeesToPaise(text: string): number | null {
    const match = text
        .trim()
        .replaceAll(',', '')
        .match(/^(\d{1,9})(?:\.(\d{1,2}))?$/);

    if (!match) {
        return null;
    }

    return Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'));
}

export function paiseToRupeesInput(paise: number): string {
    return (paise / 100).toFixed(2);
}

function parseLocalDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, (month ?? 1) - 1, day ?? 1);
}

export function formatMonth(period: string): string {
    return parseLocalDate(period).toLocaleDateString('en-IN', {
        month: 'long',
        year: 'numeric',
    });
}

export function formatMonthShort(period: string): string {
    return parseLocalDate(period).toLocaleDateString('en-IN', {
        month: 'short',
    });
}

export function formatDate(date: string | null | undefined): string {
    if (!date) {
        return '—';
    }

    return (
        date.length === 10 ? parseLocalDate(date) : new Date(date)
    ).toLocaleDateString('en-IN', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export function formatDateTime(date: string | null | undefined): string {
    if (!date) {
        return '—';
    }

    return new Date(date).toLocaleString('en-IN', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export function todayLocal(): string {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

export function currentMonth(): string {
    return todayLocal().slice(0, 7);
}

export const paymentMethodLabels: Record<string, string> = {
    cash: 'Cash',
    upi: 'UPI',
    bank_transfer: 'Bank transfer',
    cheque: 'Cheque',
    card: 'Card',
    other: 'Other',
};

export const transactionTypeLabels: Record<string, string> = {
    contribution: 'Contribution',
    expense: 'Expense',
    account_transfer: 'Account transfer',
    opening_balance: 'Opening balance',
    reversal: 'Reversal',
};
