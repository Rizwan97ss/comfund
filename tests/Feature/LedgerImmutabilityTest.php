<?php

use App\Concerns\ImmutableRecord;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DuesObligation;
use App\Models\Expense;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\MonthlyRate;
use App\Models\PaymentSubmission;
use App\Services\DuesService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('the database itself refuses to change or delete immutable rows', function (string $table, string $column) {
    $member = memberUser();
    app(DuesService::class)->generateArrears($member);
    confirmedPayment($member);

    expect(DB::table($table)->count())->toBeGreaterThan(0);

    expect(fn () => DB::table($table)->update([$column => 1]))->toThrow(QueryException::class);
    expect(fn () => DB::table($table)->delete())->toThrow(QueryException::class);
})->with([
    ['ledger_transactions', 'posted_by'],
    ['ledger_entries', 'amount_paise'],
    ['dues_obligations', 'amount_paise'],
    ['dues_allocations', 'amount_paise'],
    ['monthly_rates', 'amount_paise'],
    ['audit_logs', 'status_code'],
]);

test('the database refuses to change a reviewed submission or delete any submission', function () {
    $submission = confirmedPayment(memberUser());

    expect(fn () => DB::table('payment_submissions')->where('id', $submission->id)->update(['rejection_reason' => 'changed']))->toThrow(QueryException::class);
    expect(fn () => DB::table('payment_submissions')->where('id', $submission->id)->delete())->toThrow(QueryException::class);
});

test('the database refuses to change the amount of even a pending submission', function () {
    $member = memberUser();
    $this->actingAs($member)->postJson('/api/v1/member/submissions', submissionPayload(), idempotencyHeader())->assertCreated();

    expect(fn () => DB::table('payment_submissions')->update(['amount_paise' => 1]))->toThrow(QueryException::class);
});

test('models refuse updates and deletes before reaching the database', function (string $model) {
    $member = memberUser();
    app(DuesService::class)->generateArrears($member);
    confirmedPayment($member);

    $record = $model::query()->firstOrFail();

    expect(fn () => $record->delete())->toThrow(LogicException::class);
})->with([LedgerTransaction::class, LedgerEntry::class, DuesObligation::class, MonthlyRate::class]);

test('expenses and documents are protected the same way', function () {
    expect(class_uses_recursive(Expense::class))->toHaveKey(ImmutableRecord::class)
        ->and(class_uses_recursive(Document::class))->toHaveKey(ImmutableRecord::class)
        ->and(class_uses_recursive(AuditLog::class))->toHaveKey(ImmutableRecord::class);
});

test('every approved submission has exactly one ledger transaction', function () {
    confirmedPayment(memberUser());

    $submission = PaymentSubmission::query()->firstOrFail();

    expect(LedgerTransaction::query()->where('idempotency_key', "submission:{$submission->id}")->count())->toBe(1);
});
