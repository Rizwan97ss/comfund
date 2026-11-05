<?php

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use App\Support\BackupCipher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

test('protected endpoints refuse guests', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/v1/member/dashboard'],
    ['GET', '/api/v1/member/submissions'],
    ['POST', '/api/v1/member/submissions'],
    ['GET', '/api/v1/admin/dashboard'],
    ['GET', '/api/v1/admin/members'],
    ['POST', '/api/v1/admin/expenses'],
    ['GET', '/api/v1/admin/audit-logs'],
    ['GET', '/api/v1/documents/00000000-0000-0000-0000-000000000000'],
]);

test('every admin route refuses a member', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin/'));

    expect($routes)->not->toBeEmpty();

    $member = memberUser();

    foreach ($routes as $route) {
        $uri = preg_replace('/\{\w+\}/', '1', $route->uri());
        $method = $route->methods()[0];

        $this->actingAs($member)->json($method, '/'.$uri)->assertForbidden();
    }
});

test('admins cannot use member-only endpoints', function () {
    $this->actingAs(adminUser());

    $this->getJson('/api/v1/member/dashboard')->assertForbidden();
    $this->postJson('/api/v1/member/submissions', submissionPayload(), idempotencyHeader())->assertForbidden();
});

test('a member cannot promote themselves through their profile', function () {
    $member = memberUser();

    $this->actingAs($member)->patchJson('/api/v1/me/profile', [
        'name' => 'Same',
        'role' => 'admin',
        'status' => 'active',
        'monthly_contribution_paise' => 1,
        'email_verified_at' => null,
    ])->assertOk();

    $member->refresh();

    expect($member->role->value)->toBe('member')
        ->and($member->monthly_contribution_paise)->toBeNull();
});

test('an admin cannot escalate a member through the update endpoint', function () {
    $member = memberUser();

    $this->actingAs(adminUser())->patchJson("/api/v1/admin/members/{$member->id}", ['name' => 'Renamed', 'role' => 'admin'])->assertOk();

    expect($member->fresh()->role->value)->toBe('member');
});

test('changing a member\'s email requires it to be verified again', function () {
    $member = memberUser();

    $this->actingAs(adminUser())->patchJson("/api/v1/admin/members/{$member->id}", ['email' => 'new@example.com'])->assertOk();

    expect($member->fresh()->email_verified_at)->toBeNull();
});

test('suspending a member ends their sessions', function () {
    $member = memberUser();
    DB::table('sessions')->insert(['id' => 'live', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs(adminUser())->postJson("/api/v1/admin/members/{$member->id}/suspend")->assertOk()->assertJsonPath('data.status', 'suspended');

    expect(DB::table('sessions')->where('user_id', $member->id)->exists())->toBeFalse();
});

test('member lists cannot be used to reach an administrator record', function () {
    $otherAdmin = adminUser();

    $this->actingAs(adminUser())->getJson("/api/v1/admin/members/{$otherAdmin->id}")->assertNotFound();
    $this->patchJson("/api/v1/admin/members/{$otherAdmin->id}", ['name' => 'X'])->assertForbidden();
});

test('SQL injection in search and sort is harmless', function () {
    $member = memberUser(['name' => 'Aisha']);
    $this->actingAs(adminUser());

    $this->getJson('/api/v1/admin/members?q='.urlencode("x' OR '1'='1"))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/admin/members?sort='.urlencode('name; DROP TABLE users'))->assertUnprocessable();
    $this->getJson('/api/v1/admin/members?sort=password')->assertUnprocessable();
    $this->getJson('/api/v1/admin/members?q=Aisha')->assertOk()->assertJsonCount(1, 'data');

    expect(User::query()->whereKey($member->id)->exists())->toBeTrue();
});

test('script tags are stored as text and returned as JSON, never rendered', function () {
    $this->actingAs(adminUser())->postJson('/api/v1/admin/members', [
        'name' => '<script>alert(1)</script>',
        'email' => 'xss@example.com',
        'phone' => '9000000001',
        'joined_on' => todayString(),
    ])->assertCreated()->assertHeader('Content-Type', 'application/json');
});

test('security headers are sent', function () {
    $response = $this->get('/login')->assertOk();

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'same-origin');

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")->toContain("frame-ancestors 'none'")->toContain("object-src 'none'")
        ->and($csp)->not->toContain("script-src 'self' 'unsafe-inline'");
});

test('API responses are not cacheable', function () {
    $this->actingAs(memberUser())->getJson('/api/v1/me')->assertHeader('Cache-Control', 'no-store, private');
});

test('missing records do not reveal model names', function () {
    $this->actingAs(adminUser())->getJson('/api/v1/admin/members/99999')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not found.']);
});

test('server errors do not reveal internal details', function () {
    config(['app.debug' => false]);
    Route::get('/api/v1/test-explode', fn () => throw new RuntimeException('SELECT secret FROM table'))->middleware('api');

    $response = $this->getJson('/api/v1/test-explode')->assertServerError();

    expect($response->getContent())->not->toContain('SELECT secret')->not->toContain('RuntimeException')->not->toContain('trace');
});

test('sensitive auth endpoints are throttled', function () {
    foreach (range(1, 6) as $attempt) {
        $this->postJson('/forgot-password', ['email' => 'nobody@example.com']);
    }

    $this->postJson('/forgot-password', ['email' => 'nobody@example.com'])->assertTooManyRequests();
});

test('the API is rate limited per user', function () {
    $this->actingAs(memberUser());

    $this->getJson('/api/v1/me')->assertOk()->assertHeader('X-RateLimit-Limit', 180);
});

test('changing a password ends other sessions', function () {
    $member = memberUser(['email' => 'aisha@example.com']);
    $spa = ['Referer' => config('app.url')];

    $this->withHeaders($spa)->postJson('/login', ['email' => 'aisha@example.com', 'password' => 'password'])->assertOk();
    $this->withHeaders($spa)->getJson('/api/v1/me')->assertOk();

    // Another device changes the password; this session still holds the old hash.
    User::query()->whereKey($member->id)->firstOrFail()->forceFill(['password' => 'A-Different-Password-1'])->save();
    auth()->forgetGuards();

    $this->withHeaders($spa)->getJson('/api/v1/me')->assertUnauthorized();
});

describe('receipts', function () {
    beforeEach(fn () => Storage::fake('local'));

    function uploadReceipt(UploadedFile $file): TestResponse
    {
        return test()->post('/api/v1/member/submissions', submissionPayload([
            'payment_method' => 'upi',
            'transaction_reference' => Str::random(10),
            'receipt' => $file,
        ]), [...idempotencyHeader(), 'Accept' => 'application/json']);
    }

    test('genuine images and PDFs are accepted and stored privately under random names', function () {
        $this->actingAs(memberUser());

        uploadReceipt(UploadedFile::fake()->image('../../evil name.png', 50, 50))->assertCreated();

        $document = Document::query()->firstOrFail();

        expect($document->path)->toStartWith('documents/')
            ->and($document->path)->not->toContain('evil')
            ->and($document->original_name)->toBe('evil-name.png')
            ->and($document->mime_type)->toBe('image/png');

        Storage::disk('local')->assertExists($document->path);
    });

    test('files whose content does not match are refused', function (string $name, string $content) {
        $this->actingAs(memberUser());

        uploadReceipt(UploadedFile::fake()->createWithContent($name, $content))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('receipt');

        expect(Document::query()->count())->toBe(0);
    })->with([
        'php disguised as jpg' => ['receipt.jpg', '<?php system($_GET["c"]); ?>'],
        'php disguised as pdf' => ['receipt.pdf', '<?php phpinfo(); ?>'],
        'html disguised as png' => ['receipt.png', '<html><script>alert(1)</script></html>'],
        'bad jpeg with only a magic number' => ['receipt.jpg', "\xFF\xD8\xFF".str_repeat('A', 100)],
        'pdf with javascript' => ['receipt.pdf', "%PDF-1.4\n1 0 obj << /OpenAction << /JS (app.alert(1)) >> >> endobj\n%%EOF"],
        'executable' => ['receipt.exe', 'MZ'],
        'svg' => ['receipt.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'],
    ]);

    test('oversized files are refused', function () {
        $this->actingAs(memberUser());

        uploadReceipt(UploadedFile::fake()->image('huge.png')->size(6000))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('receipt');
    });

    test('a receipt can be downloaded by its owner and admins only, and each download is logged', function () {
        $owner = memberUser();
        $this->actingAs($owner);
        uploadReceipt(receiptPdf())->assertCreated();
        $document = Document::query()->firstOrFail();

        $response = $this->get("/api/v1/documents/{$document->uuid}")->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        expect($response->headers->get('Content-Disposition'))->toContain('attachment');

        expect(AuditLog::query()->where('action', 'document.downloaded')->where('actor_id', $owner->id)->count())->toBe(1);

        $this->actingAs(memberUser())->getJson("/api/v1/documents/{$document->uuid}")->assertNotFound();

        $this->actingAs(adminUser())->get("/api/v1/documents/{$document->uuid}")->assertOk();
    });

    test('the private disk has no public route', function () {
        expect(config('filesystems.disks.local.serve'))->toBeFalse();
        expect(collect(Route::getRoutes()->getRoutes())->map->getName())->not->toContain('storage.local');
    });
});

describe('backups', function () {
    beforeEach(function () {
        Storage::fake('local');
        config(['fund.backups.key' => 'base64:'.base64_encode(random_bytes(32))]);
    });

    test('a backup is encrypted and can be decrypted', function () {
        Process::fake(['*' => Process::result('CREATE TABLE secret_members;')]);

        $this->artisan('backup:run')->assertSuccessful();

        $file = Storage::disk('local')->files('backups')[0];
        $stored = Storage::disk('local')->get($file);

        expect($stored)->not->toContain('secret_members')
            ->and(trim(BackupCipher::decrypt($stored)))->toBe('CREATE TABLE secret_members;')
            ->and(AuditLog::query()->where('action', 'backup.completed')->exists())->toBeTrue();

        Process::assertRan(fn ($process) => ! str_contains(implode(' ', (array) $process->command), (string) config('database.connections.mysql.password') ?: 'no-password-configured'));
    });

    test('no backup is written without a key', function () {
        config(['fund.backups.key' => null]);

        $this->artisan('backup:run')->assertFailed();

        expect(Storage::disk('local')->files('backups'))->toBeEmpty();
    });

    test('a failed dump is reported and writes nothing', function () {
        Process::fake(['*' => Process::result('', 'denied', 1)]);

        $this->artisan('backup:run')->assertFailed();

        expect(Storage::disk('local')->files('backups'))->toBeEmpty()
            ->and(AuditLog::query()->where('action', 'backup.failed')->exists())->toBeTrue();
    });
});
