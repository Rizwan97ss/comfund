<?php

use App\Models\FundAccountBalance;
use App\Models\User;
use App\Services\DuesService;
use App\Services\LedgerService;
use App\Support\BusinessCalendar;
use Illuminate\Support\Facades\Storage;

test('a fund statement shows opening, collections, expenses and closing', function () {
    $admin = adminUser();
    $member = memberUser();
    $yesterday = BusinessCalendar::today()->subDay()->format('Y-m-d');

    $this->actingAs($admin)->postJson('/api/v1/admin/opening-balances', [
        'fund_id' => fund('sadaqah')->id,
        'financial_account_id' => bankAccount()->id,
        'amount_paise' => 100000,
        'as_of' => $yesterday,
    ])->assertCreated();

    confirmedPayment($member, 'sadaqah', 30000);

    $this->postJson('/api/v1/admin/expenses', [
        'fund_id' => fund('sadaqah')->id,
        'financial_account_id' => bankAccount()->id,
        'amount_paise' => 20000,
        'beneficiary' => 'Food Bank',
        'purpose' => 'Food',
        'expense_date' => todayString(),
        'payment_method' => 'cash',
    ], idempotencyHeader())->assertCreated();

    $this->getJson('/api/v1/admin/reports/statement?from='.todayString().'&to='.todayString())
        ->assertOk()
        ->assertJsonPath('data.funds.2.name', 'Sadaqah')
        ->assertJsonPath('data.funds.2.opening_paise', 100000)
        ->assertJsonPath('data.funds.2.collections_paise', 30000)
        ->assertJsonPath('data.funds.2.expenses_paise', 20000)
        ->assertJsonPath('data.funds.2.closing_paise', 110000);
});

test('the admin dashboard totals exclude pending and rejected money', function () {
    Storage::fake('local');
    $member = memberUser();

    confirmedPayment($member, 'sadaqah', 30000);

    $this->actingAs($member)->postJson('/api/v1/member/submissions', submissionPayload(['amount_paise' => 99999]), idempotencyHeader())->assertCreated();

    $this->actingAs(adminUser())->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.totals.collections_paise', 30000)
        ->assertJsonPath('data.totals.available_paise', 30000)
        ->assertJsonPath('data.totals.donations_paise', 30000)
        ->assertJsonPath('data.pending.count', 1)
        ->assertJsonPath('data.pending.amount_paise', 99999);
});

test('the dashboard shows this month\'s expected and collected dues', function () {
    $member = User::factory()->joinedThisMonth()->create();
    app(DuesService::class)->generateArrears($member);
    confirmedPayment($member, amount: 4000);

    $this->actingAs(adminUser())->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.this_month.expected_paise', 10000)
        ->assertJsonPath('data.this_month.collected_paise', 4000)
        ->assertJsonPath('data.this_month.outstanding_paise', 6000)
        ->assertJsonPath('data.members.with_arrears', 1);
});

test('a member statement contains only that member\'s confirmed payments', function () {
    $member = memberUser();
    $other = memberUser();
    confirmedPayment($member, 'zakat', 12300);
    confirmedPayment($other, 'zakat', 45600);

    $this->actingAs($member)->getJson('/api/v1/member/statement')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.amount_paise', 12300)
        ->assertJsonPath('data.0.fund', 'Zakat');
});

test('the integrity check confirms balances equal the ledger, and notices when they do not', function () {
    confirmedPayment(memberUser(), 'zakat', 12300);
    $ledger = app(LedgerService::class);

    expect($ledger->reconcileBalances()['balanced'])->toBeTrue();
    $this->artisan('ledger:reconcile')->assertSuccessful();

    FundAccountBalance::query()->where('fund_id', fund('zakat')->id)->where('financial_account_id', bankAccount()->id)->update(['balance_paise' => 999999]);

    expect($ledger->reconcileBalances()['balanced'])->toBeFalse();
    $this->artisan('ledger:reconcile')->assertFailed();
});
