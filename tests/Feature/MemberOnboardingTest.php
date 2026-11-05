<?php

use App\Enums\AccountStatus;
use App\Exceptions\FinancialRuleViolation;
use App\Models\AuditLog;
use App\Models\DuesObligation;
use App\Models\MemberInvitation;
use App\Models\User;
use App\Notifications\MemberInvited;
use App\Services\InvitationService;
use App\Support\BusinessCalendar;
use Illuminate\Support\Facades\Notification;

function invite(array $overrides = []): array
{
    Notification::fake();

    $admin = adminUser();
    $response = test()->actingAs($admin)->postJson('/api/v1/admin/members', [
        'name' => 'Yusuf Ansari',
        'email' => 'Yusuf@Example.com',
        'phone' => '9876543210',
        'address' => '12 Masjid Road',
        'joined_on' => todayString(),
        ...$overrides,
    ])->assertCreated();

    $member = User::query()->findOrFail($response->json('data.id'));
    $token = '';

    Notification::assertSentTo($member, MemberInvited::class, function (MemberInvited $notification) use (&$token, $member) {
        preg_match('~/invitation/(\w+)~', $notification->toMail($member)->actionUrl, $matches);
        $token = $matches[1];

        return true;
    });

    auth()->forgetGuards();

    return [$member, $token, $admin];
}

test('an admin registers a member who starts invited and cannot sign in', function () {
    [$member] = invite();

    expect($member->status)->toBe(AccountStatus::Invited)
        ->and($member->password)->toBeNull()
        ->and($member->email)->toBe('yusuf@example.com')
        ->and($member->member_code)->toStartWith('MCF-');
});

test('only an admin can register a member', function () {
    $this->actingAs(memberUser())->postJson('/api/v1/admin/members', [])->assertForbidden();
});

test('member registration validates its fields', function () {
    $this->actingAs(adminUser())->postJson('/api/v1/admin/members', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'phone', 'joined_on']);
});

test('the invitation token is stored only as a hash', function () {
    [$member, $token] = invite();

    $invitation = MemberInvitation::query()->where('user_id', $member->id)->firstOrFail();

    expect($invitation->token_hash)->toBe(hash('sha256', $token))->not->toBe($token);
});

test('a member completes the whole onboarding flow and lands signed in', function () {
    [$member, $token] = invite();
    $url = "/api/v1/invitations/{$token}";

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.step', 'verify')
        ->assertJsonPath('data.profile', null)
        ->assertJsonMissing(['phone' => '9876543210']);

    $this->postJson("{$url}/verify", ['phone_last_four' => '3210'])
        ->assertOk()
        ->assertJsonPath('data.step', 'profile')
        ->assertJsonPath('data.profile.phone', '9876543210');

    $this->postJson("{$url}/profile", ['name' => 'Yusuf A. Ansari', 'phone' => '9876543210', 'address' => '14 Masjid Road'])
        ->assertOk()
        ->assertJsonPath('data.step', 'password');

    $this->postJson("{$url}/activate", ['password' => 'A-Strong-Password-1', 'password_confirmation' => 'A-Strong-Password-1'])
        ->assertOk()
        ->assertJsonPath('data.activated', true);

    $member->refresh();

    expect($member->status)->toBe(AccountStatus::Active)
        ->and($member->name)->toBe('Yusuf A. Ansari')
        ->and($member->address)->toBe('14 Masjid Road')
        ->and($member->email_verified_at)->not->toBeNull()
        ->and(password_verify('A-Strong-Password-1', (string) $member->password))->toBeTrue();

    $this->assertAuthenticatedAs($member, 'web');
    $this->getJson($url)->assertNotFound();
});

test('steps cannot be skipped', function () {
    [, $token] = invite();
    $url = "/api/v1/invitations/{$token}";

    $this->postJson("{$url}/profile", ['name' => 'X', 'phone' => '9876543210', 'address' => 'Y'])->assertConflict();
    $this->postJson("{$url}/activate", ['password' => 'A-Strong-Password-1', 'password_confirmation' => 'A-Strong-Password-1'])->assertConflict();
});

test('the invitation is revoked after too many wrong verification attempts', function () {
    [$member, $token] = invite();
    $url = "/api/v1/invitations/{$token}";

    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])->postJson("{$url}/verify", ['phone_last_four' => '0000'])->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->postJson("{$url}/verify", ['phone_last_four' => '3210'])->assertNotFound();

    expect($member->fresh()->status)->toBe(AccountStatus::Invited);
});

test('an expired invitation cannot be used', function () {
    [, $token] = invite();

    $this->travel(8)->days();

    $this->getJson("/api/v1/invitations/{$token}")->assertNotFound();
});

test('resending invalidates the earlier link', function () {
    [$member, $token, $admin] = invite();

    $this->actingAs($admin)->postJson("/api/v1/admin/members/{$member->id}/invitation")->assertOk();

    auth()->forgetGuards();
    $this->getJson("/api/v1/invitations/{$token}")->assertNotFound();
});

test('the invitation token never appears in the audit log', function () {
    [, $token] = invite();

    $this->getJson("/api/v1/invitations/{$token}")->assertOk();
    $this->get("/invitation/{$token}")->assertOk();

    expect(AuditLog::query()->where('route', 'like', "%{$token}%")->orWhere('payload', 'like', "%{$token}%")->exists())->toBeFalse();
});

test('registering with a past joining date creates arrears from that month', function () {
    invite(['joined_on' => BusinessCalendar::currentMonth()->subMonths(2)->format('Y-m-d')]);

    expect(DuesObligation::query()->count())->toBe(3);
});

test('the service refuses to resend to a member who is already active', function () {
    $this->expectException(FinancialRuleViolation::class);

    app(InvitationService::class)->resend(memberUser(), adminUser());
});
