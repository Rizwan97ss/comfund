<?php

use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Support\ImmutableTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('every API request is logged with who, what and the outcome', function () {
    $member = memberUser();

    $this->actingAs($member)->getJson('/api/v1/member/dashboard', ['User-Agent' => 'TestBrowser/1.0'])->assertOk();

    $entry = AuditLog::query()->where('route', 'api/v1/member/dashboard')->firstOrFail();

    expect($entry->actor_id)->toBe($member->id)
        ->and($entry->actor_role)->toBe('member')
        ->and($entry->http_method)->toBe('GET')
        ->and($entry->status_code)->toBe(200)
        ->and($entry->user_agent)->toBe('TestBrowser/1.0')
        ->and($entry->ip_address)->toBe('127.0.0.1')
        ->and($entry->request_id)->not->toBeNull();
});

test('data changes are logged with before and after values', function () {
    $member = memberUser(['name' => 'Old Name']);

    $this->actingAs($member)->patchJson('/api/v1/me/profile', ['name' => 'New Name'])->assertOk();

    $entry = AuditLog::query()->where('action', 'users.updated')->firstOrFail();
    $payload = json_decode((string) $entry->payload, true);

    expect($entry->subject_type)->toBe('User')
        ->and($entry->subject_id)->toBe((string) $member->id)
        ->and($payload['before']['name'])->toBe('Old Name')
        ->and($payload['after']['name'])->toBe('New Name');
});

test('secrets and encrypted personal data are never written to the log', function () {
    $member = memberUser();

    $this->actingAs($member)->patchJson('/api/v1/me/profile', ['name' => 'X', 'address' => 'Secret Street 1'])->assertOk();

    $this->app['auth']->forgetGuards();
    $this->postJson('/login', ['email' => $member->email, 'password' => 'super-secret-attempt']);

    $everything = AuditLog::query()->pluck('payload')->implode(' ');

    expect($everything)->not->toContain('super-secret-attempt')
        ->not->toContain('Secret Street 1')
        ->not->toContain('$2y$');
});

test('a refused request is logged as a security event', function () {
    $member = memberUser();

    $this->actingAs($member)->getJson('/api/v1/admin/dashboard')->assertForbidden();

    expect(AuditLog::query()->where('action', 'security.role_denied')->where('actor_id', $member->id)->exists())->toBeTrue();

    $http = AuditLog::query()->where('route', 'api/v1/admin/dashboard')->firstOrFail();
    expect($http->category)->toBe('security')->and($http->status_code)->toBe(403);
});

test('a denied policy check is logged', function () {
    Storage::fake('local');
    $owner = memberUser();
    $this->actingAs($owner);
    $uuid = $this->postJson('/api/v1/member/submissions', submissionPayload(), idempotencyHeader())->json('data.uuid');

    $stranger = memberUser();
    $this->actingAs($stranger)->getJson("/api/v1/member/submissions/{$uuid}")->assertNotFound();

    expect(AuditLog::query()->where('action', 'security.authorization_denied')->where('actor_id', $stranger->id)->exists())->toBeTrue();
});

test('validation failures and their inputs are logged, with secrets redacted', function () {
    $this->actingAs(adminUser())->postJson('/api/v1/admin/members', ['name' => 'Someone', 'password' => 'hunter2-secret'])->assertUnprocessable();

    $entry = AuditLog::query()->where('route', 'api/v1/admin/members')->firstOrFail();

    expect($entry->status_code)->toBe(422)
        ->and($entry->payload)->toContain('Someone')
        ->and($entry->payload)->not->toContain('hunter2-secret');
});

test('the client reports page views, which are logged', function () {
    $member = memberUser();

    $this->actingAs($member)->postJson('/api/v1/activity/page-view', ['path' => '/contributions'])->assertNoContent();
    $this->postJson('/api/v1/activity/page-view', ['path' => 'javascript:alert(1)'])->assertUnprocessable();

    $entry = AuditLog::query()->where('action', 'page.viewed')->firstOrFail();

    expect($entry->payload)->toContain('/contributions');
});

test('financial actions are logged in the same transaction as the money', function () {
    $submission = confirmedPayment(memberUser());

    foreach (['payment_submissions.created', 'payment_submissions.updated', 'ledger_transactions.created', 'ledger_entries.created', 'fund_account_balances.updated'] as $action) {
        expect(AuditLog::query()->where('action', $action)->where('category', 'finance')->exists())->toBeTrue("Missing {$action}");
    }

    expect($submission->status->value)->toBe('approved');
});

test('an operation that fails leaves no audit entries claiming it happened', function () {
    fundWithBalance('sadaqah', 100);
    $before = AuditLog::query()->where('action', 'expenses.created')->count();

    $this->actingAs(adminUser())->postJson('/api/v1/admin/expenses', [
        'fund_id' => fund('sadaqah')->id,
        'financial_account_id' => bankAccount()->id,
        'amount_paise' => 5000,
        'beneficiary' => 'X',
        'purpose' => 'Y',
        'expense_date' => todayString(),
        'payment_method' => 'cash',
    ], idempotencyHeader())->assertUnprocessable();

    expect(AuditLog::query()->where('action', 'expenses.created')->count())->toBe($before)
        ->and(AuditLog::query()->where('action', 'like', 'ledger_%')->where('payload', 'like', '%-5000%')->exists())->toBeFalse();
});

test('sequences have no gaps and the chain verifies', function () {
    confirmedPayment(memberUser());
    $this->actingAs(adminUser())->getJson('/api/v1/admin/dashboard')->assertOk();

    $sequences = AuditLog::query()->orderBy('sequence')->pluck('sequence')->all();

    expect($sequences)->toBe(range(1, count($sequences)));

    $this->getJson('/api/v1/admin/audit-logs/verify')->assertOk()->assertJsonPath('data.valid', true);
    $this->artisan('audit:verify')->assertSuccessful();
});

test('altering a row is detected, even by someone who bypassed the triggers', function () {
    confirmedPayment(memberUser());
    ImmutableTable::release('audit_logs');

    $victim = AuditLog::query()->where('action', 'ledger_entries.created')->firstOrFail();
    DB::table('audit_logs')->where('id', $victim->id)->update(['actor_label' => 'Someone Else']);

    $result = app(AuditLogger::class)->verifyChain();

    expect($result['valid'])->toBeFalse()
        ->and($result['broken_at'])->toBe($victim->sequence)
        ->and($result['reason'])->toBe('A row was altered after it was written.');

    $this->artisan('audit:verify')->assertFailed();
});

test('deleting a row from the middle or the end is detected', function (string $position) {
    confirmedPayment(memberUser());
    ImmutableTable::release('audit_logs');

    $query = AuditLog::query();
    $sequence = $position === 'middle' ? (int) floor($query->max('sequence') / 2) : (int) $query->max('sequence');
    DB::table('audit_logs')->where('sequence', $sequence)->delete();

    expect(app(AuditLogger::class)->verifyChain()['valid'])->toBeFalse();
})->with(['middle', 'end']);

test('only admins can read the audit log', function () {
    $this->actingAs(memberUser())->getJson('/api/v1/admin/audit-logs')->assertForbidden();
    $this->actingAs(memberUser())->getJson('/api/v1/admin/audit-logs/verify')->assertForbidden();

    confirmedPayment(memberUser());

    $this->actingAs(adminUser())->getJson('/api/v1/admin/audit-logs?category=finance')
        ->assertOk()
        ->assertJsonPath('data.0.category', 'finance');
});

test('the audit log can be searched safely', function () {
    $this->actingAs(adminUser());

    $this->getJson('/api/v1/admin/audit-logs?q='.urlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/admin/audit-logs?category=not-a-category')->assertUnprocessable();
});
