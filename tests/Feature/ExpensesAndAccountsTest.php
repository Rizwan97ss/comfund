<?php

use App\Models\AccountTransfer;
use App\Models\Expense;
use App\Models\FundAccountBalance;
use App\Models\LedgerEntry;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Storage;

/**
 * @return array<string, mixed>
 */
function expensePayload(array $overrides = []): array
{
    return [
        'fund_id' => fund('sadaqah')->id,
        'financial_account_id' => bankAccount()->id,
        'amount_paise' => 10000,
        'beneficiary' => 'Local Food Bank',
        'purpose' => 'Food parcels',
        'expense_date' => todayString(),
        'payment_method' => 'cash',
        ...$overrides,
    ];
}

function balance(string $fund, $account): int
{
    return app(LedgerService::class)->balanceOf(fund($fund)->id, $account->id);
}

test('an expense reduces the balance and is posted to the ledger', function () {
    fundWithBalance('sadaqah', 50000);
    $this->actingAs(adminUser());

    $this->postJson('/api/v1/admin/expenses', expensePayload(), idempotencyHeader())
        ->assertCreated()
        ->assertJsonPath('data.beneficiary', 'Local Food Bank');

    expect(balance('sadaqah', bankAccount()))->toBe(40000)
        ->and(LedgerEntry::query()->where('kind', 'expense')->sum('amount_paise'))->toEqual(-10000);
});

test('spending more than the fund holds in that account is refused', function () {
    fundWithBalance('sadaqah', 5000);
    $this->actingAs(adminUser());

    $this->postJson('/api/v1/admin/expenses', expensePayload(), idempotencyHeader())->assertUnprocessable();

    expect(balance('sadaqah', bankAccount()))->toBe(5000)->and(Expense::query()->count())->toBe(0);
});

test('money in another fund cannot pay for an expense', function () {
    fundWithBalance('zakat', 100000);
    $this->actingAs(adminUser());

    $this->postJson('/api/v1/admin/expenses', expensePayload(), idempotencyHeader())->assertUnprocessable();

    expect(balance('zakat', bankAccount()))->toBe(100000);
});

test('money in another account cannot pay for an expense', function () {
    fundWithBalance('sadaqah', 100000, cashAccount());
    $this->actingAs(adminUser());

    $this->postJson('/api/v1/admin/expenses', expensePayload(), idempotencyHeader())->assertUnprocessable();
});

test('a restricted fund can only be spent on its allowed purposes', function () {
    fundWithBalance('zakat', 100000);
    $this->actingAs(adminUser());

    $this->postJson('/api/v1/admin/expenses', expensePayload(['fund_id' => fund('zakat')->id, 'purpose' => 'New carpets']), idempotencyHeader())
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Zakat is a restricted fund and cannot be spent on this purpose.');

    $this->postJson('/api/v1/admin/expenses', expensePayload(['fund_id' => fund('zakat')->id, 'purpose' => 'The poor (Fuqara)']), idempotencyHeader())
        ->assertCreated();
});

test('a deactivated purpose can no longer be spent on', function () {
    fundWithBalance('zakat', 100000);
    $this->actingAs(adminUser());
    $purpose = fund('zakat')->purposes()->where('name', 'The poor (Fuqara)')->firstOrFail();

    $this->patchJson("/api/v1/admin/fund-purposes/{$purpose->id}", ['is_active' => false])->assertOk();

    $this->postJson('/api/v1/admin/expenses', expensePayload(['fund_id' => fund('zakat')->id, 'purpose' => 'The poor (Fuqara)']), idempotencyHeader())
        ->assertUnprocessable();
});

test('replaying an expense does not spend twice', function () {
    fundWithBalance('sadaqah', 50000);
    $this->actingAs(adminUser());
    $key = idempotencyHeader();

    $this->postJson('/api/v1/admin/expenses', expensePayload(), $key)->assertCreated();
    $this->postJson('/api/v1/admin/expenses', expensePayload(), $key)->assertOk();

    expect(balance('sadaqah', bankAccount()))->toBe(40000);
});

test('expenses can carry supporting documents', function () {
    Storage::fake('local');
    fundWithBalance('sadaqah', 50000);
    $this->actingAs(adminUser());

    $this->post('/api/v1/admin/expenses', expensePayload(['documents' => [receiptPdf()]]), [...idempotencyHeader(), 'Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonCount(1, 'data.documents');
});

test('expenses can be filtered by fund, beneficiary and date', function () {
    fundWithBalance('sadaqah', 50000);
    $this->actingAs(adminUser());
    $this->postJson('/api/v1/admin/expenses', expensePayload(), idempotencyHeader())->assertCreated();
    $this->postJson('/api/v1/admin/expenses', expensePayload(['beneficiary' => 'Someone Else']), idempotencyHeader())->assertCreated();

    $this->getJson('/api/v1/admin/expenses?beneficiary=Food')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/admin/expenses?fund_id='.fund('zakat')->id)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/admin/expenses?to=2000-01-01')->assertOk()->assertJsonCount(0, 'data');
});

test('members cannot see or record expenses', function () {
    $this->actingAs(memberUser());

    $this->getJson('/api/v1/admin/expenses')->assertForbidden();
    $this->postJson('/api/v1/admin/expenses', expensePayload(), idempotencyHeader())->assertForbidden();
});

test('reversing an expense restores the balance and adds entries instead of editing', function () {
    fundWithBalance('sadaqah', 50000);
    $this->actingAs(adminUser());
    $ledgerUuid = $this->postJson('/api/v1/admin/expenses', expensePayload(), idempotencyHeader())->json('data.ledger_uuid');
    $entries = LedgerEntry::query()->count();

    $this->postJson("/api/v1/admin/ledger/{$ledgerUuid}/reverse", ['reason' => 'Paid in error.'])
        ->assertCreated()
        ->assertJsonPath('data.type', 'reversal');

    expect(balance('sadaqah', bankAccount()))->toBe(50000)
        ->and(LedgerEntry::query()->count())->toBe($entries + 1);

    $this->getJson('/api/v1/admin/expenses')->assertJsonPath('data.0.is_reversed', true);
});

test('a transaction can only be reversed once and a reversal cannot be reversed', function () {
    fundWithBalance('sadaqah', 50000);
    $this->actingAs(adminUser());
    $ledgerUuid = $this->postJson('/api/v1/admin/expenses', expensePayload(), idempotencyHeader())->json('data.ledger_uuid');

    $reversal = $this->postJson("/api/v1/admin/ledger/{$ledgerUuid}/reverse", ['reason' => 'Paid in error.'])->json('data.uuid');

    $this->postJson("/api/v1/admin/ledger/{$ledgerUuid}/reverse", ['reason' => 'Paid in error.'])->assertConflict();
    $this->postJson("/api/v1/admin/ledger/{$reversal}/reverse", ['reason' => 'Undo the undo.'])->assertConflict();
    expect(balance('sadaqah', bankAccount()))->toBe(50000);
});

test('reversing income that has already been spent is refused', function () {
    $member = memberUser();
    $this->actingAs(adminUser());
    $submission = confirmedPayment($member, 'sadaqah', 10000);
    $this->postJson('/api/v1/admin/expenses', expensePayload(['amount_paise' => 10000]), idempotencyHeader())->assertCreated();

    $this->postJson("/api/v1/admin/ledger/{$submission->ledgerTransaction->uuid}/reverse", ['reason' => 'Bounced cheque.'])
        ->assertUnprocessable();
});

test('a transfer moves money between accounts without changing the fund, income or expenses', function () {
    fundWithBalance('sadaqah', 50000, cashAccount());
    $this->actingAs(adminUser());

    $this->postJson('/api/v1/admin/transfers', [
        'fund_id' => fund('sadaqah')->id,
        'from_account_id' => cashAccount()->id,
        'to_account_id' => bankAccount()->id,
        'amount_paise' => 20000,
        'transfer_date' => todayString(),
    ], idempotencyHeader())->assertCreated();

    expect(balance('sadaqah', cashAccount()))->toBe(30000)
        ->and(balance('sadaqah', bankAccount()))->toBe(20000);

    $this->getJson('/api/v1/admin/reports/statement?from='.todayString().'&to='.todayString())
        ->assertOk()
        ->assertJsonPath('data.funds.2.collections_paise', 0)
        ->assertJsonPath('data.funds.2.expenses_paise', 0)
        ->assertJsonPath('data.funds.2.closing_paise', 50000);
});

test('a transfer cannot overdraw the source or target the same account', function () {
    fundWithBalance('sadaqah', 1000, cashAccount());
    $this->actingAs(adminUser());
    $base = ['fund_id' => fund('sadaqah')->id, 'from_account_id' => cashAccount()->id, 'transfer_date' => todayString()];

    $this->postJson('/api/v1/admin/transfers', [...$base, 'to_account_id' => bankAccount()->id, 'amount_paise' => 2000], idempotencyHeader())->assertUnprocessable();
    $this->postJson('/api/v1/admin/transfers', [...$base, 'to_account_id' => cashAccount()->id, 'amount_paise' => 500], idempotencyHeader())
        ->assertUnprocessable()->assertJsonValidationErrors('to_account_id');
    expect(AccountTransfer::query()->count())->toBe(0);
});

test('no endpoint moves money between funds', function () {
    $this->actingAs(adminUser());

    $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri());

    expect($routes->filter(fn (string $uri) => str_contains($uri, 'fund-transfer')))->toBeEmpty();

    fundWithBalance('sadaqah', 1000);

    $this->postJson('/api/v1/admin/transfers', [
        'fund_id' => fund('sadaqah')->id,
        'to_fund_id' => fund('zakat')->id,
        'from_account_id' => bankAccount()->id,
        'to_account_id' => cashAccount()->id,
        'amount_paise' => 500,
        'transfer_date' => todayString(),
    ], idempotencyHeader())->assertCreated();

    expect(balance('zakat', cashAccount()))->toBe(0)->and(balance('sadaqah', cashAccount()))->toBe(500);
});

test('an opening balance can only be recorded once unless it is reversed', function () {
    $this->actingAs(adminUser());
    $payload = ['fund_id' => fund('sadaqah')->id, 'financial_account_id' => bankAccount()->id, 'amount_paise' => 5000, 'as_of' => todayString()];

    $uuid = $this->postJson('/api/v1/admin/opening-balances', $payload)->assertCreated()->json('data.uuid');
    $this->postJson('/api/v1/admin/opening-balances', $payload)->assertConflict();

    $this->postJson("/api/v1/admin/ledger/{$uuid}/reverse", ['reason' => 'Wrong amount.'])->assertCreated();
    $this->postJson('/api/v1/admin/opening-balances', [...$payload, 'amount_paise' => 6000])->assertCreated();

    expect(balance('sadaqah', bankAccount()))->toBe(6000);
});

test('a new account gets a balance row for every fund', function () {
    $this->actingAs(adminUser());

    $id = $this->postJson('/api/v1/admin/accounts', ['name' => 'UPI Wallet', 'type' => 'digital'])->assertCreated()->json('data.id');

    expect(FundAccountBalance::query()->where('financial_account_id', $id)->count())->toBe(6);
});

test('a reconciliation records the difference between the books and the statement', function () {
    fundWithBalance('sadaqah', 50000);
    $this->actingAs(adminUser());

    $this->postJson('/api/v1/admin/reconciliations', [
        'financial_account_id' => bankAccount()->id,
        'as_of' => todayString(),
        'statement_balance_paise' => 49000,
    ])->assertCreated()
        ->assertJsonPath('data.book_balance_paise', 50000)
        ->assertJsonPath('data.difference_paise', -1000);
});
