<?php

use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\AccountService;
use App\Services\SubmissionService;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function adminUser(): User
{
    return User::factory()->admin()->create();
}

function memberUser(array $attributes = []): User
{
    return User::factory()->joinedThisMonth()->create($attributes);
}

function fund(string $code): Fund
{
    return Fund::query()->where('code', $code)->firstOrFail();
}

function bankAccount(): FinancialAccount
{
    return FinancialAccount::query()->where('type', 'bank')->firstOrFail();
}

function cashAccount(): FinancialAccount
{
    return FinancialAccount::query()->where('type', 'cash')->firstOrFail();
}

function todayString(): string
{
    return BusinessCalendar::today()->format('Y-m-d');
}

function thisMonth(): string
{
    return BusinessCalendar::currentMonth()->format('Y-m');
}

function idempotencyHeader(?string $key = null): array
{
    return ['Idempotency-Key' => $key ?? (string) Str::uuid()];
}

function receiptPdf(string $body = 'receipt'): UploadedFile
{
    return UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n% {$body}\n%%EOF\n");
}

/**
 * @return array<string, mixed>
 */
function submissionPayload(array $overrides = []): array
{
    return [
        'fund_id' => fund('sadaqah')->id,
        'amount_paise' => 50000,
        'payment_date' => todayString(),
        'payment_method' => 'cash',
        ...$overrides,
    ];
}

/**
 * A confirmed payment, recorded the same way a real one is.
 */
function confirmedPayment(User $member, string $fundCode = 'monthly_fund', int $amount = 10000, ?string $month = null, ?FinancialAccount $account = null, ?string $reference = null): PaymentSubmission
{
    return app(SubmissionService::class)->recordManualEntry(adminUser(), [
        'member_id' => $member->id,
        'fund_id' => fund($fundCode)->id,
        'financial_account_id' => ($account ?? bankAccount())->id,
        'amount_paise' => $amount,
        'applicable_month' => $fundCode === 'monthly_fund' ? ($month ?? thisMonth()) : null,
        'payment_date' => todayString(),
        'payment_method' => $reference === null ? 'cash' : 'upi',
        'transaction_reference' => $reference,
        'attribution_note' => 'Recorded for a test.',
    ], null, (string) Str::uuid());
}

function fundWithBalance(string $code, int $amount, ?FinancialAccount $account = null): void
{
    app(AccountService::class)->recordOpeningBalance(adminUser(), [
        'fund_id' => fund($code)->id,
        'financial_account_id' => ($account ?? bankAccount())->id,
        'amount_paise' => $amount,
        'as_of' => todayString(),
    ]);
}
