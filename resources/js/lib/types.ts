export type Paginated<T> = {
    data: T[];
    meta?: {
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    current_page?: number;
    last_page?: number;
    total?: number;
};

export type CurrentUser = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    address: string | null;
    role: 'admin' | 'member';
    status: 'pending' | 'invited' | 'active' | 'suspended';
    member_code: string | null;
    joined_on: string | null;
    monthly_contribution_paise: number | null;
    email_verified: boolean;
    two_factor_enabled: boolean;
};

export type Fund = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    is_mandatory: boolean;
    is_restricted: boolean;
    purposes: { id: number; name: string }[];
};

export type ReferenceData = { funds: Fund[]; payment_methods: string[] };

export type Document = {
    uuid: string;
    name: string;
    mime_type: string;
    size_bytes: number;
};

export type SubmissionStatus = 'pending' | 'approved' | 'rejected';

export type Submission = {
    uuid: string;
    fund: { id: number; name: string; code: string };
    amount_paise: number;
    applicable_month: string | null;
    payment_date: string;
    payment_method: string;
    transaction_reference: string | null;
    member_note: string | null;
    status: SubmissionStatus;
    source: 'member' | 'admin_manual';
    rejection_reason: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    submitted_at: string | null;
    is_reversed: boolean;
    document: Document | null;
    member?: { id: number; name: string; member_code: string | null };
    submitted_by?: string;
    attribution_note?: string | null;
    account?: string | null;
    ledger_uuid?: string | null;
    same_receipt_count?: number | null;
};

export type DuesSummary = {
    obligated_paise: number;
    paid_paise: number;
    waived_paise: number;
    outstanding_paise: number;
    advance_paise: number;
    paid_months: number;
    partial_months: number;
    unpaid_months: number;
    waived_months: number;
};

export type DuesMonth = {
    id: number;
    period: string;
    amount_paise: number;
    paid_paise: number;
    waived_paise: number;
    outstanding_paise: number;
    status: 'paid' | 'partial' | 'unpaid' | 'waived';
};

export type MemberDashboard = {
    as_of: string;
    total_approved_paise: number;
    voluntary_paise: number;
    by_fund: { id: number; code: string; name: string; total_paise: number }[];
    dues: DuesSummary;
    pending: { count: number; amount_paise: number };
    rejected_count: number;
    current_rate_paise: number;
};

export type FundBalance = {
    id: number;
    code: string;
    name: string;
    is_restricted: boolean;
    balance_paise: number;
};
export type AccountBalance = {
    id: number;
    name: string;
    type: string;
    is_active: boolean;
    balance_paise: number;
};

export type AdminDashboard = {
    as_of: string;
    totals: {
        collections_paise: number;
        expenses_paise: number;
        opening_paise: number;
        available_paise: number;
        monthly_dues_paise: number;
        donations_paise: number;
    };
    this_month: {
        period: string;
        expected_paise: number;
        collected_paise: number;
        waived_paise: number;
        outstanding_paise: number;
    };
    pending: { count: number; amount_paise: number };
    members: {
        active: number;
        invited: number;
        pending_approval: number;
        with_arrears: number;
    };
    funds: FundBalance[];
    accounts: AccountBalance[];
    trend: {
        month: string;
        collections_paise: number;
        expenses_paise: number;
    }[];
};

export type Member = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    address: string | null;
    status: 'pending' | 'invited' | 'active' | 'suspended';
    member_code: string | null;
    joined_on: string | null;
    monthly_contribution_paise: number | null;
    activated_at: string | null;
    outstanding_paise?: number;
};

export type LedgerEntry = {
    id: number;
    occurred_on: string;
    fund?: string;
    account?: string;
    kind: string;
    amount_paise: number;
    narration?: string;
    transaction_type?: string;
};

export type LedgerTransaction = {
    uuid: string;
    type: string;
    occurred_on: string;
    narration: string;
    member?: string | null;
    posted_by?: string | null;
    reversed_by?: string | null;
    reverses?: string | null;
    entries?: LedgerEntry[];
    created_at: string | null;
};

export type Expense = {
    uuid: string;
    fund: string;
    account: string;
    amount_paise: number;
    beneficiary: string;
    purpose: string;
    expense_date: string;
    payment_method: string;
    transaction_reference: string | null;
    description: string | null;
    created_by: string;
    documents: Document[];
    ledger_uuid: string;
    is_reversed: boolean;
};

export type StatementRow = {
    id: number;
    name: string;
    opening_paise: number;
    opening_entries_paise: number;
    collections_paise: number;
    expenses_paise: number;
    transfers_net_paise: number;
    closing_paise: number;
};

export type Statement = {
    from: string;
    to: string;
    funds: StatementRow[];
    accounts: StatementRow[];
};

export type AuditEntry = {
    sequence: number;
    occurred_at: string;
    category: string;
    action: string;
    severity: string;
    actor: string | null;
    actor_role: string | null;
    subject_type: string | null;
    subject_id: string | null;
    ip_address: string | null;
    user_agent: string | null;
    request_id: string | null;
    http_method: string | null;
    route: string | null;
    status_code: number | null;
    payload: Record<string, unknown> | null;
    hash: string;
};

export type ChainVerification = {
    valid: boolean;
    checked: number;
    last_sequence: number;
    last_hash: string;
    broken_at: number | null;
    reason: string | null;
};

export type SessionInfo = {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    last_active_at: string;
    is_current: boolean;
};

export type InvitationState = {
    first_name: string;
    email_hint: string;
    expires_at: string;
    step: 'verify' | 'profile' | 'password';
    profile: {
        name: string;
        phone: string | null;
        address: string | null;
    } | null;
};
