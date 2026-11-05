<?php

use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\FundAccountBalance;
use App\Models\LedgerEntry;
use App\Models\PaymentSubmission;
use App\Notifications\SubmissionReviewed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

function submit(array $overrides = [], ?array $headers = null): TestResponse
{
    return test()->postJson('/api/v1/member/submissions', submissionPayload($overrides), $headers ?? idempotencyHeader());
}

test('a submission is pending and does not touch the ledger or any balance', function () {
    $member = memberUser();

    $this->actingAs($member);
    submit()->assertCreated()->assertJsonPath('data.status', 'pending');

    expect(LedgerEntry::query()->count())->toBe(0)
        ->and((int) FundAccountBalance::query()->sum('balance_paise'))->toBe(0);

    $this->getJson('/api/v1/member/dashboard')
        ->assertOk()
        ->assertJsonPath('data.total_approved_paise', 0)
        ->assertJsonPath('data.pending.count', 1)
        ->assertJsonPath('data.pending.amount_paise', 50000);
});

test('a submission requires the idempotency key', function () {
    $this->actingAs(memberUser());

    submit(headers: [])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
});

test('replaying the same key does not create a second payment', function () {
    $this->actingAs(memberUser());
    $key = idempotencyHeader();

    $first = submit(headers: $key)->assertCreated();
    $second = submit(headers: $key)->assertOk();

    expect($second->json('data.uuid'))->toBe($first->json('data.uuid'))
        ->and(PaymentSubmission::query()->count())->toBe(1);
});

test('another user cannot reuse someone else\'s key', function () {
    $key = idempotencyHeader();
    $this->actingAs(memberUser());
    submit(headers: $key)->assertCreated();

    $this->actingAs(memberUser());
    submit(headers: $key)->assertConflict();
});

test('the same reference cannot be submitted twice, even by different members', function () {
    Storage::fake('local');
    $reference = ['payment_method' => 'upi', 'transaction_reference' => 'upi-123', 'receipt' => receiptPdf()];

    $this->actingAs(memberUser());
    submit($reference)->assertCreated();

    $this->actingAs(memberUser());
    submit(['payment_method' => 'upi', 'transaction_reference' => 'UPI-123', 'receipt' => receiptPdf('other')])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'A payment with this method and transaction reference has already been recorded.');
});

test('monthly fund payments need a month, and other funds refuse one', function () {
    $this->actingAs(memberUser());

    submit(['fund_id' => fund('monthly_fund')->id])->assertUnprocessable()->assertJsonValidationErrors('applicable_month');
    submit(['applicable_month' => thisMonth()])->assertUnprocessable()->assertJsonValidationErrors('applicable_month');
    submit(['fund_id' => fund('monthly_fund')->id, 'applicable_month' => thisMonth()])->assertCreated();
});

test('a month before joining is refused', function () {
    $this->actingAs(memberUser());

    submit(['fund_id' => fund('monthly_fund')->id, 'applicable_month' => now()->subYears(2)->format('Y-m')])
        ->assertUnprocessable()
        ->assertJsonPath('errors.applicable_month.0', 'The month cannot be before you joined.');
});

test('invalid submissions are refused', function (array $overrides, string $field) {
    $this->actingAs(memberUser());

    submit($overrides)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'zero amount' => [['amount_paise' => 0], 'amount_paise'],
    'negative amount' => [['amount_paise' => -100], 'amount_paise'],
    'fractional amount' => [['amount_paise' => 10.5], 'amount_paise'],
    'amount too large' => [['amount_paise' => 1_000_000_001], 'amount_paise'],
    'future date' => [['payment_date' => now()->addDays(3)->format('Y-m-d')], 'payment_date'],
    'unknown method' => [['payment_method' => 'bitcoin'], 'payment_method'],
    'unknown fund' => [['fund_id' => 9999], 'fund_id'],
    'reference missing for upi' => [['payment_method' => 'upi', 'receipt' => null], 'transaction_reference'],
    'receipt missing for upi' => [['payment_method' => 'upi', 'transaction_reference' => 'ABC'], 'receipt'],
    'reference with markup' => [['transaction_reference' => '<script>'], 'transaction_reference'],
]);

test('forged status and reviewer fields in the request are ignored', function () {
    $this->actingAs(memberUser());

    submit(['status' => 'approved', 'reviewed_by' => 1, 'ledger_transaction_id' => 1, 'member_id' => 99])->assertCreated();

    expect(PaymentSubmission::query()->firstOrFail()->status)->toBe(SubmissionStatus::Pending);
});

test('members see only their own submissions', function () {
    $owner = memberUser();
    $this->actingAs($owner);
    $uuid = submit()->json('data.uuid');

    $stranger = memberUser();
    $this->actingAs($stranger);

    $this->getJson("/api/v1/member/submissions/{$uuid}")->assertNotFound();
    $this->getJson('/api/v1/member/submissions')->assertOk()->assertJsonCount(0, 'data');

    $this->actingAs($owner);
    $this->getJson("/api/v1/member/submissions/{$uuid}")->assertOk();
});

test('there is no route to edit or delete a submission', function () {
    $this->actingAs(memberUser());
    $uuid = submit()->json('data.uuid');

    $this->patchJson("/api/v1/member/submissions/{$uuid}", ['amount_paise' => 1])->assertStatus(405);
    $this->deleteJson("/api/v1/member/submissions/{$uuid}")->assertStatus(405);

    $this->actingAs(adminUser());
    $this->patchJson("/api/v1/admin/submissions/{$uuid}", ['amount_paise' => 1])->assertStatus(405);
    $this->deleteJson("/api/v1/admin/submissions/{$uuid}")->assertStatus(405);
});

test('approval posts to the ledger exactly once and records who did it', function () {
    $member = memberUser();
    $this->actingAs($member);
    $uuid = submit()->json('data.uuid');

    $admin = adminUser();
    $this->actingAs($admin);

    $this->postJson("/api/v1/admin/submissions/{$uuid}/approve", ['financial_account_id' => bankAccount()->id])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.reviewer', $admin->name);

    $submission = PaymentSubmission::query()->where('uuid', $uuid)->firstOrFail();

    expect($submission->reviewed_by)->toBe($admin->id)
        ->and($submission->reviewed_at)->not->toBeNull()
        ->and(LedgerEntry::query()->sum('amount_paise'))->toEqual(50000);

    $this->postJson("/api/v1/admin/submissions/{$uuid}/approve", ['financial_account_id' => bankAccount()->id])
        ->assertConflict();

    expect(LedgerEntry::query()->count())->toBe(1);
    Notification::assertSentTo($member, SubmissionReviewed::class);
});

test('rejection needs a reason, and a rejected payment never reaches the ledger', function () {
    $this->actingAs(memberUser());
    $uuid = submit()->json('data.uuid');

    $admin = adminUser();
    $this->actingAs($admin);

    $this->postJson("/api/v1/admin/submissions/{$uuid}/reject", [])->assertUnprocessable()->assertJsonValidationErrors('reason');

    $this->postJson("/api/v1/admin/submissions/{$uuid}/reject", ['reason' => 'The receipt is unreadable.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejection_reason', 'The receipt is unreadable.')
        ->assertJsonPath('data.reviewer', $admin->name);

    expect(LedgerEntry::query()->count())->toBe(0);

    $this->postJson("/api/v1/admin/submissions/{$uuid}/approve", ['financial_account_id' => bankAccount()->id])->assertConflict();
    expect(LedgerEntry::query()->count())->toBe(0);
});

test('a rejected payment can be resubmitted as a new record with the same reference', function () {
    $member = memberUser();
    $this->actingAs($member);
    $payment = ['payment_method' => 'upi', 'transaction_reference' => 'UPI-9'];
    $uuid = submit([...$payment, 'receipt' => receiptPdf()])->json('data.uuid');

    $this->actingAs(adminUser());
    $this->postJson("/api/v1/admin/submissions/{$uuid}/reject", ['reason' => 'Wrong amount entered.'])->assertOk();

    $this->actingAs($member);
    $second = submit([...$payment, 'receipt' => receiptPdf('v2'), 'resubmission_of' => $uuid])->assertCreated();

    expect($second->json('data.uuid'))->not->toBe($uuid)
        ->and(PaymentSubmission::query()->count())->toBe(2)
        ->and(PaymentSubmission::query()->where('uuid', $uuid)->value('status'))->toBe(SubmissionStatus::Rejected);
});

test('only your own rejected payment can be resubmitted', function () {
    $owner = memberUser();
    $this->actingAs($owner);
    $uuid = submit()->json('data.uuid');

    $this->actingAs($owner);
    submit(['resubmission_of' => $uuid])->assertUnprocessable();

    $this->actingAs(adminUser());
    $this->postJson("/api/v1/admin/submissions/{$uuid}/reject", ['reason' => 'Wrong amount entered.'])->assertOk();

    $this->actingAs(memberUser());
    submit(['resubmission_of' => $uuid])->assertUnprocessable();
});

test('a member cannot approve or reject', function () {
    $member = memberUser();
    $this->actingAs($member);
    $uuid = submit()->json('data.uuid');

    $this->postJson("/api/v1/admin/submissions/{$uuid}/approve", ['financial_account_id' => bankAccount()->id])->assertForbidden();
    $this->postJson("/api/v1/admin/submissions/{$uuid}/reject", ['reason' => 'Trying it on.'])->assertForbidden();

    expect(PaymentSubmission::query()->where('uuid', $uuid)->value('status'))->toBe(SubmissionStatus::Pending);
});

test('a manual entry is confirmed immediately and requires attribution', function () {
    $member = memberUser();
    $admin = adminUser();
    $this->actingAs($admin);

    $payload = [
        'member_id' => $member->id,
        'fund_id' => fund('zakat')->id,
        'financial_account_id' => cashAccount()->id,
        'amount_paise' => 25000,
        'payment_date' => todayString(),
        'payment_method' => 'cash',
    ];

    $this->postJson('/api/v1/admin/manual-entries', $payload, idempotencyHeader())
        ->assertUnprocessable()->assertJsonValidationErrors('attribution_note');

    $this->postJson('/api/v1/admin/manual-entries', [...$payload, 'attribution_note' => 'Cash handed over after Jumuah.'], idempotencyHeader())
        ->assertCreated()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.source', 'admin_manual')
        ->assertJsonPath('data.submitted_by', $admin->name)
        ->assertJsonPath('data.attribution_note', 'Cash handed over after Jumuah.');

    expect(LedgerEntry::query()->where('member_id', $member->id)->sum('amount_paise'))->toEqual(25000)
        ->and(AuditLog::query()->where('action', 'payment_submissions.created')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

test('a manual entry cannot be recorded against an administrator', function () {
    $admin = adminUser();
    $this->actingAs($admin);

    $this->postJson('/api/v1/admin/manual-entries', [
        'member_id' => $admin->id,
        'fund_id' => fund('zakat')->id,
        'financial_account_id' => cashAccount()->id,
        'amount_paise' => 25000,
        'payment_date' => todayString(),
        'payment_method' => 'cash',
        'attribution_note' => 'Attempting a self entry.',
    ], idempotencyHeader())->assertUnprocessable()->assertJsonValidationErrors('member_id');
});

test('the review screen flags a receipt that was already used on another submission', function () {
    $receipt = UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n% same\n%%EOF\n");
    $this->actingAs(memberUser());
    submit(['receipt' => $receipt])->assertCreated();

    $copy = UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\n% same\n%%EOF\n");
    $this->actingAs(memberUser());
    $uuid = submit(['receipt' => $copy])->json('data.uuid');

    $this->actingAs(adminUser());
    $this->getJson("/api/v1/admin/submissions/{$uuid}")->assertOk()->assertJsonPath('data.same_receipt_count', 1);
});

test('a failed submission leaves no orphaned receipt file behind', function () {
    $this->actingAs(memberUser());
    submit(['payment_method' => 'upi', 'transaction_reference' => 'DUP-1', 'receipt' => receiptPdf()])->assertCreated();
    $filesBefore = count(Storage::disk('local')->allFiles());

    $this->actingAs(memberUser());
    submit(['payment_method' => 'upi', 'transaction_reference' => 'DUP-1', 'receipt' => receiptPdf('x')])->assertUnprocessable();

    expect(count(Storage::disk('local')->allFiles()))->toBe($filesBefore);
});

test('uuid route keys are not guessable from sequential ids', function () {
    $this->actingAs(memberUser());
    $uuid = submit()->json('data.uuid');

    expect(Str::isUuid($uuid))->toBeTrue();
    $this->getJson('/api/v1/member/submissions/1')->assertNotFound();
});
