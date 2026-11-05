<?php

use App\Jobs\GenerateMonthlyDues;
use App\Models\AuditLog;
use App\Models\DuesAllocation;
use App\Models\DuesObligation;
use App\Models\LedgerTransaction;
use App\Models\MonthlyRate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DuesService;
use App\Services\SubmissionService;
use App\Support\BusinessCalendar;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Notification::fake());

function month(int $offset = 0): string
{
    return BusinessCalendar::currentMonth()->addMonths($offset)->format('Y-m');
}

function monthsAgoMember(int $months, array $attributes = []): User
{
    $member = User::factory()->create(['joined_on' => BusinessCalendar::currentMonth()->subMonths($months)->format('Y-m-d'), ...$attributes]);
    app(DuesService::class)->generateArrears($member);

    return $member;
}

function summary(User $member): array
{
    return app(DuesService::class)->summaryFor($member);
}

test('generation is idempotent', function () {
    $member = memberUser();
    $dues = app(DuesService::class);

    expect($dues->generateForPeriod(BusinessCalendar::currentMonth()))->toBe(1)
        ->and($dues->generateForPeriod(BusinessCalendar::currentMonth()))->toBe(0)
        ->and(DuesObligation::query()->where('member_id', $member->id)->count())->toBe(1);
});

test('generation uses the default ₹100 rate', function () {
    $member = monthsAgoMember(0);

    expect(summary($member)['outstanding_paise'])->toBe(10000);
});

test('suspended members and future joiners get no new dues', function () {
    User::factory()->suspended()->joinedThisMonth()->create();
    User::factory()->create(['joined_on' => BusinessCalendar::currentMonth()->addMonth()->format('Y-m-d')]);

    expect(app(DuesService::class)->generateForPeriod(BusinessCalendar::currentMonth()))->toBe(0);
});

test('changing the rate does not alter dues already generated', function () {
    $member = monthsAgoMember(1);
    $admin = adminUser();

    $this->actingAs($admin)->postJson('/api/v1/admin/dues/rates', [
        'amount_paise' => 20000,
        'effective_month' => month(1),
    ])->assertCreated();

    expect(DuesObligation::query()->where('member_id', $member->id)->pluck('amount_paise')->all())->toBe([10000, 10000]);

    $this->travelTo(BusinessCalendar::currentMonth()->addMonth()->addDays(3));

    app(DuesService::class)->generateArrears($member);

    expect(DuesObligation::query()->where('member_id', $member->id)->orderBy('period')->pluck('amount_paise')->all())->toBe([10000, 10000, 20000]);
});

test('a rate cannot be backdated or set twice for a month', function () {
    $this->actingAs(adminUser());

    $this->postJson('/api/v1/admin/dues/rates', ['amount_paise' => 5000, 'effective_month' => month(-1)])->assertUnprocessable();
    $this->postJson('/api/v1/admin/dues/rates', ['amount_paise' => 5000, 'effective_month' => month(1)])->assertCreated();
    $this->postJson('/api/v1/admin/dues/rates', ['amount_paise' => 6000, 'effective_month' => month(1)])->assertUnprocessable();

    expect(MonthlyRate::query()->count())->toBe(2);
});

test('a member-specific contribution overrides the standard rate', function () {
    $member = monthsAgoMember(0, ['monthly_contribution_paise' => 50000]);

    expect(summary($member)['obligated_paise'])->toBe(50000);
});

test('a partial payment leaves the remainder outstanding', function () {
    $member = monthsAgoMember(0);

    confirmedPayment($member, amount: 4000);

    expect(summary($member))->toMatchArray(['paid_paise' => 4000, 'outstanding_paise' => 6000, 'partial_months' => 1, 'paid_months' => 0]);
});

test('payment goes to the chosen month first, then the oldest arrears', function () {
    $member = monthsAgoMember(2);

    confirmedPayment($member, amount: 15000, month: month(-1));

    $paid = app(DuesService::class)->obligationsFor($member)->mapWithKeys(fn (DuesObligation $o) => [$o->period->format('Y-m') => $o->paidPaise()])->all();

    expect($paid)->toBe([month(-2) => 5000, month(-1) => 10000, month(0) => 0]);
});

test('an overpayment becomes an advance that pays later months when they are generated', function () {
    $member = monthsAgoMember(0);

    confirmedPayment($member, amount: 25000);

    expect(summary($member))->toMatchArray(['paid_months' => 1, 'advance_paise' => 15000, 'outstanding_paise' => 0]);

    $this->travelTo(BusinessCalendar::currentMonth()->addMonth()->addDays(2));
    app(DuesService::class)->generateArrears($member);

    expect(summary($member))->toMatchArray(['paid_months' => 2, 'advance_paise' => 5000, 'outstanding_paise' => 0]);
});

test('voluntary donations never pay dues', function () {
    $member = monthsAgoMember(0);

    confirmedPayment($member, 'sadaqah', 50000);

    expect(summary($member))->toMatchArray(['paid_paise' => 0, 'outstanding_paise' => 10000, 'advance_paise' => 0]);
});

test('pending and rejected payments never reduce what is owed', function () {
    Storage::fake('local');
    $member = monthsAgoMember(0);
    $this->actingAs($member);

    $uuid = $this->postJson('/api/v1/member/submissions', submissionPayload(['fund_id' => fund('monthly_fund')->id, 'applicable_month' => thisMonth(), 'amount_paise' => 10000]), idempotencyHeader())->json('data.uuid');

    expect(summary($member)['outstanding_paise'])->toBe(10000);

    $this->actingAs(adminUser());
    $this->postJson("/api/v1/admin/submissions/{$uuid}/reject", ['reason' => 'No such payment.'])->assertOk();

    expect(summary($member)['outstanding_paise'])->toBe(10000);
});

test('a waiver reduces what is owed and cannot exceed it', function () {
    $member = monthsAgoMember(0);
    $obligation = DuesObligation::query()->where('member_id', $member->id)->firstOrFail();
    $this->actingAs(adminUser());

    $this->postJson("/api/v1/admin/dues/obligations/{$obligation->id}/waive", ['amount_paise' => 10001, 'reason' => 'Hardship this month.'])->assertUnprocessable();
    $this->postJson("/api/v1/admin/dues/obligations/{$obligation->id}/waive", ['amount_paise' => 10000])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson("/api/v1/admin/dues/obligations/{$obligation->id}/waive", ['amount_paise' => 10000, 'reason' => 'Hardship this month.'])->assertCreated();

    expect(summary($member))->toMatchArray(['outstanding_paise' => 0, 'waived_months' => 1, 'waived_paise' => 10000]);
});

test('reversing a waiver restores the amount owed, once only', function () {
    $member = monthsAgoMember(0);
    $obligation = DuesObligation::query()->where('member_id', $member->id)->firstOrFail();
    $this->actingAs(adminUser());

    $id = $this->postJson("/api/v1/admin/dues/obligations/{$obligation->id}/waive", ['amount_paise' => 10000, 'reason' => 'Hardship this month.'])->json('data.id');

    $this->postJson("/api/v1/admin/dues/waivers/{$id}/reverse", ['reason' => 'Waived in error.'])->assertCreated();
    expect(summary($member)['outstanding_paise'])->toBe(10000);

    $this->postJson("/api/v1/admin/dues/waivers/{$id}/reverse", ['reason' => 'Waived in error.'])->assertConflict();
});

test('reversing a payment puts the dues back and keeps the history', function () {
    $member = monthsAgoMember(0);
    $submission = confirmedPayment($member);
    $allocations = DuesAllocation::query()->count();

    expect(summary($member)['outstanding_paise'])->toBe(0);

    app(SubmissionService::class)->reverseTransaction($submission->ledgerTransaction, 'Cheque bounced.', adminUser());

    expect(summary($member))->toMatchArray(['outstanding_paise' => 10000, 'paid_paise' => 0, 'advance_paise' => 0])
        ->and(DuesAllocation::query()->count())->toBe($allocations + 1);
});

test('reversing a payment that funded an advance takes back only what is needed', function () {
    $member = monthsAgoMember(1);
    $first = confirmedPayment($member, amount: 10000, month: month(-1));
    confirmedPayment($member, amount: 15000, month: month(0));

    expect(summary($member))->toMatchArray(['outstanding_paise' => 0, 'advance_paise' => 5000]);

    app(SubmissionService::class)->reverseTransaction($first->ledgerTransaction, 'Duplicate entry.', adminUser());

    expect(summary($member))->toMatchArray(['outstanding_paise' => 5000, 'advance_paise' => 0, 'paid_paise' => 15000]);
});

test('the member sees their own dues month by month', function () {
    $member = monthsAgoMember(1);
    confirmedPayment($member, amount: 10000, month: month(-1));

    $this->actingAs($member)->getJson('/api/v1/member/dues')
        ->assertOk()
        ->assertJsonPath('data.summary.outstanding_paise', 10000)
        ->assertJsonPath('data.months.0.period', month(0))
        ->assertJsonPath('data.months.0.status', 'unpaid')
        ->assertJsonPath('data.months.1.status', 'paid');
});

test('the unpaid report lists only members who owe, largest first', function () {
    $small = monthsAgoMember(0, ['name' => 'Small']);
    $large = monthsAgoMember(3, ['name' => 'Large']);
    $settled = monthsAgoMember(0, ['name' => 'Settled']);
    confirmedPayment($settled);

    $this->actingAs(adminUser())->getJson('/api/v1/admin/dues/unpaid')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Large')
        ->assertJsonPath('data.0.outstanding_paise', 40000)
        ->assertJsonPath('data.1.name', 'Small');
});

test('the scheduled command queues generation for the current month', function () {
    Bus::fake();

    $this->artisan('dues:generate')->assertSuccessful();

    Bus::assertDispatched(GenerateMonthlyDues::class, fn (GenerateMonthlyDues $job) => $job->period === thisMonth());
});

test('the command refuses a malformed period', function () {
    $this->artisan('dues:generate --period=2026-13')->assertFailed();
});

test('the generation job creates dues and records that it ran', function () {
    $member = memberUser();

    (new GenerateMonthlyDues(thisMonth()))->handle(app(DuesService::class), app(AuditLogger::class));

    expect(DuesObligation::query()->where('member_id', $member->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'dues.generated')->exists())->toBeTrue();
    expect(LedgerTransaction::query()->count())->toBe(0);
});
