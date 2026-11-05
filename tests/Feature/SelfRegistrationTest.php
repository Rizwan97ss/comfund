<?php

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\DuesObligation;
use App\Models\User;
use App\Notifications\RegistrationApproved;
use App\Notifications\RegistrationReceived;
use App\Notifications\RegistrationRejected;
use App\Services\DuesService;
use App\Support\BusinessCalendar;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function registrationData(array $overrides = []): array
{
    return [
        'name' => 'Fatima Khan',
        'email' => 'Fatima@Example.com',
        'phone' => '9876500001',
        'address' => '7 Masjid Road',
        'password' => 'a-Long-Passphrase-41',
        'password_confirmation' => 'a-Long-Passphrase-41',
        ...$overrides,
    ];
}

function pendingMember(array $overrides = []): User
{
    test()->postJson('/api/v1/registrations', registrationData($overrides))->assertCreated();

    return User::query()->where('phone', $overrides['phone'] ?? '9876500001')->firstOrFail();
}

beforeEach(fn () => Notification::fake());

test('registering creates a pending member and tells the admins', function () {
    $admin = adminUser();
    $member = pendingMember();

    expect($member->status)->toBe(AccountStatus::Pending)
        ->and($member->role)->toBe(UserRole::Member)
        ->and($member->email)->toBe('fatima@example.com')
        ->and($member->member_code)->toBeNull()
        ->and($member->email_verified_at)->toBeNull();

    Notification::assertSentTo($admin, RegistrationReceived::class);
});

test('registering does not sign the person in', function () {
    pendingMember();

    $this->assertGuest('web');
});

test('a registrant cannot choose their own role or status', function () {
    $member = pendingMember([
        'role' => 'admin',
        'status' => 'active',
        'email_verified_at' => now()->toIso8601String(),
    ]);

    expect($member->role)->toBe(UserRole::Member)
        ->and($member->status)->toBe(AccountStatus::Pending)
        ->and($member->email_verified_at)->toBeNull();
});

test('registration validates its fields', function () {
    $this->postJson('/api/v1/registrations', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'phone', 'address', 'password']);
});

test('registration refuses an email or mobile number already in use', function () {
    pendingMember();

    $this->postJson('/api/v1/registrations', registrationData())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'phone']);
});

test('registration is rate limited', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/registrations', []);
    }

    $this->postJson('/api/v1/registrations', [])->assertTooManyRequests();
});

test('a pending member is told they are waiting only with the right password', function () {
    pendingMember();

    $this->postJson('/login', ['email' => 'fatima@example.com', 'password' => 'a-Long-Passphrase-41'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Your registration is waiting for approval by an administrator.');

    $this->assertGuest('web');

    $response = $this->postJson('/login', ['email' => 'fatima@example.com', 'password' => 'wrong-password'])
        ->assertUnprocessable();

    expect($response->json('errors.email.0'))->not->toContain('approval');
});

test('a pending member owes no dues until approved', function () {
    $member = pendingMember();

    // Even with a joining date set, the monthly run must skip a pending account.
    $member->forceFill(['joined_on' => todayString()])->save();
    app(DuesService::class)->generateForPeriod(BusinessCalendar::currentMonth());

    expect(DuesObligation::query()->where('member_id', $member->id)->exists())->toBeFalse();
});

test('money cannot be recorded against a pending member', function () {
    $member = pendingMember();

    $this->actingAs(adminUser())->postJson('/api/v1/admin/manual-entries', ['member_id' => $member->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['member_id']);
});

test('an admin approves a registration and sets the joining terms', function () {
    $member = pendingMember();

    $this->actingAs(adminUser())->postJson("/api/v1/admin/members/{$member->id}/approve", [
        'joined_on' => todayString(),
        'monthly_contribution_paise' => 50000,
    ])->assertOk()->assertJsonPath('data.status', 'active');

    $member->refresh();

    expect($member->status)->toBe(AccountStatus::Active)
        ->and($member->member_code)->toBe(sprintf('MCF-%05d', $member->id))
        ->and($member->joined_on->format('Y-m-d'))->toBe(todayString())
        ->and($member->monthly_contribution_paise)->toBe(50000)
        ->and($member->activated_at)->not->toBeNull()
        ->and($member->email_verified_at)->toBeNull()
        ->and(DuesObligation::query()->where('member_id', $member->id)->count())->toBe(1);

    Notification::assertSentTo($member, RegistrationApproved::class);
    Notification::assertSentTo($member, VerifyEmail::class);
});

test('an approved member signs in with their own password but stays locked until verified', function () {
    $member = pendingMember();

    $this->actingAs(adminUser())->postJson("/api/v1/admin/members/{$member->id}/approve", ['joined_on' => todayString()])->assertOk();
    auth()->forgetGuards();
    $this->app['auth']->guard('web')->logout();

    $this->postJson('/login', ['email' => 'fatima@example.com', 'password' => 'a-Long-Passphrase-41'])->assertOk();

    $this->assertAuthenticatedAs($member->fresh(), 'web');
    $this->getJson('/api/v1/member/dashboard')->assertForbidden();
});

test('approval needs a valid joining date', function () {
    $member = pendingMember();

    $this->actingAs(adminUser())->postJson("/api/v1/admin/members/{$member->id}/approve", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['joined_on']);

    $this->postJson("/api/v1/admin/members/{$member->id}/approve", ['joined_on' => now()->addYear()->format('Y-m-d')])
        ->assertUnprocessable();

    expect($member->fresh()->status)->toBe(AccountStatus::Pending);
});

test('only a pending registration can be approved or rejected', function () {
    $admin = adminUser();
    $active = memberUser();

    $this->actingAs($admin)->postJson("/api/v1/admin/members/{$active->id}/approve", ['joined_on' => todayString()])->assertStatus(409);
    $this->postJson("/api/v1/admin/members/{$active->id}/reject", ['reason' => 'Not known to us'])->assertStatus(409);

    expect(User::query()->whereKey($active->id)->exists())->toBeTrue();
});

test('only an admin can review a registration', function () {
    $member = pendingMember();

    $this->actingAs(memberUser())->postJson("/api/v1/admin/members/{$member->id}/approve", ['joined_on' => todayString()])->assertForbidden();
    $this->postJson("/api/v1/admin/members/{$member->id}/reject", ['reason' => 'Not known to us'])->assertForbidden();

    expect($member->fresh()->status)->toBe(AccountStatus::Pending);
});

test('rejecting removes the account, tells the person and keeps an audit record', function () {
    $member = pendingMember();

    $this->actingAs(adminUser())->postJson("/api/v1/admin/members/{$member->id}/reject", ['reason' => 'Not known to us'])->assertOk();

    expect(User::query()->whereKey($member->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'users.registration_rejected')->where('subject_id', (string) $member->id)->exists())->toBeTrue();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        RegistrationRejected::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'fatima@example.com',
    );

    auth()->forgetGuards();
    $this->app['auth']->guard('web')->logout();
    pendingMember();
});

test('rejecting needs a reason', function () {
    $member = pendingMember();

    $this->actingAs(adminUser())->postJson("/api/v1/admin/members/{$member->id}/reject", ['reason' => 'no'])
        ->assertUnprocessable();

    expect($member->fresh())->not->toBeNull();
});

test('admins can list pending registrations and see the count on the dashboard', function () {
    $member = pendingMember();
    memberUser();

    $this->actingAs(adminUser());

    $this->getJson('/api/v1/admin/members?status=pending')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $member->id);

    $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonPath('data.members.pending_approval', 1);
});
