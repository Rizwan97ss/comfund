<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

test('an active member can sign in through the SPA and read their own profile', function () {
    $member = memberUser(['email' => 'aisha@example.com']);
    $spa = ['Referer' => config('app.url'), 'Accept' => 'application/json'];

    $this->withHeaders($spa)->postJson('/login', ['email' => 'aisha@example.com', 'password' => 'password'])->assertOk();

    $this->withHeaders($spa)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'aisha@example.com')
        ->assertJsonPath('data.role', 'member')
        ->assertJsonMissingPath('data.password');

    expect(AuditLog::query()->where('action', 'auth.login')->where('actor_id', $member->id)->exists())->toBeTrue();
});

test('email is matched case-insensitively at sign in', function () {
    memberUser(['email' => 'aisha@example.com']);

    $this->postJson('/login', ['email' => 'AISHA@example.com', 'password' => 'password'])->assertOk();
});

test('accounts that are not active cannot sign in', function (string $state) {
    $user = User::factory()->{$state}()->create(['password' => 'password']);

    $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();

    $this->assertGuest('web');
})->with(['invited', 'suspended']);

test('a failed sign in is audited without the attempted password', function () {
    memberUser(['email' => 'aisha@example.com']);

    $this->postJson('/login', ['email' => 'aisha@example.com', 'password' => 'not-the-password-xyz'])->assertUnprocessable();

    $entry = AuditLog::query()->where('action', 'auth.login_failed')->firstOrFail();

    expect($entry->payload)->toContain('aisha@example.com');
    expect(AuditLog::query()->where('payload', 'like', '%not-the-password-xyz%')->exists())->toBeFalse();
});

test('signing in with an unknown email is an ordinary failure', function () {
    $this->postJson('/login', ['email' => 'nobody@example.com', 'password' => 'anything'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('an invited account with no password fails like any other', function () {
    $user = User::factory()->invited()->create();

    $this->postJson('/login', ['email' => $user->email, 'password' => 'anything'])->assertUnprocessable();
});

test('posting to the login form while already signed in answers in JSON', function () {
    $this->actingAs(memberUser());

    $this->postJson('/login', ['email' => 'x@example.com', 'password' => 'x'])
        ->assertOk()
        ->assertExactJson(['two_factor' => false]);
});

test('sign in works from the address in APP_URL', function () {
    config(['app.url' => 'http://fund-management.test', 'sanctum.stateful' => ['fund-management.test']]);
    memberUser(['email' => 'aisha@example.com']);
    $spa = ['Referer' => 'http://fund-management.test/login'];

    $this->withHeaders($spa)->postJson('/login', ['email' => 'aisha@example.com', 'password' => 'password'])->assertOk();
    $this->withHeaders($spa)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'aisha@example.com');
});

test('sign in is throttled after repeated failures', function () {
    memberUser(['email' => 'aisha@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/login', ['email' => 'aisha@example.com', 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson('/login', ['email' => 'aisha@example.com', 'password' => 'password'])->assertTooManyRequests();
});

test('Fortify registration stays off, so nobody is signed in by registering', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();

    $this->postJson('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertStatus(405);

    expect(User::query()->where('email', 'x@example.com')->exists())->toBeFalse();
});

test('a user with two-factor enabled must pass the challenge before being signed in', function () {
    $member = User::factory()->withTwoFactor()->create();

    $this->postJson('/login', ['email' => $member->email, 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('two_factor', true);

    $this->assertGuest('web');
});

test('unauthenticated API requests get a 401, not a redirect', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
    $this->get('/api/v1/member/dashboard')->assertUnauthorized();
});

test('an unverified user can identify themselves but cannot reach financial data', function () {
    $member = User::factory()->unverified()->create();

    $this->actingAs($member)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email_verified', false);
    $this->actingAs($member)->getJson('/api/v1/member/dashboard')->assertForbidden();
});

test('a suspended member with a live session is refused immediately', function () {
    $member = User::factory()->suspended()->create();

    $this->actingAs($member)->getJson('/api/v1/member/dashboard')->assertForbidden();
});

test('a member can reset a forgotten password with the emailed token', function () {
    Notification::fake();
    $member = memberUser(['email' => 'aisha@example.com']);

    $this->postJson('/forgot-password', ['email' => 'aisha@example.com'])->assertOk();

    Notification::assertSentTo($member, ResetPassword::class, function (ResetPassword $notification) use ($member) {
        $this->postJson('/reset-password', [
            'token' => $notification->token,
            'email' => $member->email,
            'password' => 'A-New-Password-123',
            'password_confirmation' => 'A-New-Password-123',
        ])->assertOk();

        return true;
    });

    $this->postJson('/login', ['email' => 'aisha@example.com', 'password' => 'A-New-Password-123'])->assertOk();
    expect(AuditLog::query()->where('action', 'auth.password_reset')->exists())->toBeTrue();
});

test('a user can only list and end their own sessions', function () {
    $member = memberUser();
    $other = memberUser();

    DB::table('sessions')->insert([
        ['id' => 'mine', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time(), 'ip_address' => '1.1.1.1', 'user_agent' => 'a'],
        ['id' => 'theirs', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time(), 'ip_address' => '2.2.2.2', 'user_agent' => 'b'],
    ]);

    $this->actingAs($member)->getJson('/api/v1/me/sessions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissing(['id' => 'mine']);

    $this->actingAs($member)->deleteJson('/api/v1/me/sessions/'.hash('sha256', 'theirs'))->assertNotFound();
    $this->actingAs($member)->deleteJson('/api/v1/me/sessions/'.hash('sha256', 'mine'))->assertNoContent();

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['theirs']);
});
