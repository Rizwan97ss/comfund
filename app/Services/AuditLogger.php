<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the tamper-evident audit log. Each row carries an HMAC over its contents
 * and the previous row's hash, so editing, removing or inserting a row breaks the
 * chain (see self::verifyChain()).
 *
 * Inside atomic() entries are buffered and written last, as part of the same
 * database transaction: they commit or roll back with the change they describe,
 * and the single chain lock is always taken after the row locks, so that it cannot
 * deadlock against them.
 */
class AuditLogger
{
    public const REDACTED = '[REDACTED]';

    private const SENSITIVE_KEYS = '/(password|passwd|token|secret|recovery|remember|otp|cookie|authorization|^code$|two_factor|^address$|idempotency|duplicate_guard|signature|^hash$)/i';

    private int $atomicDepth = 0;

    /** @var list<array<string, string|int|null>> */
    private array $buffer = [];

    /**
     * Run the callback in a database transaction whose audit entries commit with it.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @param  int<1, max>  $attempts
     * @return TReturn
     */
    public function atomic(Closure $callback, int $attempts = 3): mixed
    {
        if ($this->atomicDepth > 0) {
            return $callback();
        }

        try {
            return DB::transaction(function () use ($callback) {
                $this->buffer = [];
                $this->atomicDepth = 1;

                $result = $callback();

                $this->atomicDepth = 0;
                $this->flush();

                return $result;
            }, $attempts);
        } finally {
            $this->atomicDepth = 0;
            $this->buffer = [];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{http_method?: string, route?: string|null, status_code?: int}  $http
     */
    public function record(
        string $action,
        string $category = 'data',
        string $severity = 'info',
        ?Model $subject = null,
        array $payload = [],
        array $http = [],
        ?User $actor = null,
    ): void {
        $actor ??= $this->currentActor();
        $request = app()->bound('request') ? request() : null;
        $occurredAt = now('UTC')->format('Y-m-d H:i:s.u');

        $this->buffer[] = [
            'occurred_at' => $occurredAt,
            'category' => $category,
            'action' => Str::limit($action, 117),
            'severity' => $severity,
            'actor_id' => $actor?->getKey(),
            'actor_label' => $actor === null ? null : Str::limit($actor->name.' <'.$actor->email.'>', 250),
            'actor_role' => $actor?->role?->value,
            'subject_type' => $subject === null ? null : class_basename($subject),
            'subject_id' => $subject === null ? null : (string) $subject->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request === null ? null : $this->cleanText((string) $request->userAgent(), 512),
            'request_id' => Context::get('request_id'),
            'http_method' => $http['http_method'] ?? null,
            'route' => isset($http['route']) ? $this->cleanText($http['route'], 191) : null,
            'status_code' => $http['status_code'] ?? null,
            'payload' => $payload === [] ? null : $this->encode($this->redact($payload)),
        ];

        if ($this->atomicDepth === 0) {
            DB::transaction(fn () => $this->flush());
        }
    }

    /**
     * Replace the values of sensitive keys, at any depth.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEYS, $key) === 1) {
                $data[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }

    /**
     * Recompute the whole chain and report the first row that does not match.
     *
     * @return array{valid: bool, checked: int, last_sequence: int, last_hash: string, broken_at: int|null, reason: string|null}
     */
    public function verifyChain(): array
    {
        $previousHash = str_repeat('0', 64);
        $expectedSequence = 1;
        $brokenAt = null;
        $reason = null;

        foreach (DB::table('audit_logs')->lazyById(500, 'sequence') as $row) {
            $entry = (array) $row;

            if ((int) $entry['sequence'] !== $expectedSequence) {
                [$brokenAt, $reason] = [$expectedSequence, 'A row is missing from the chain.'];
                break;
            }

            if ($entry['previous_hash'] !== $previousHash) {
                [$brokenAt, $reason] = [$expectedSequence, 'A row does not link to the row before it.'];
                break;
            }

            if (! hash_equals($this->hashFor($entry, (int) $entry['sequence'], $previousHash), $entry['hash'])) {
                [$brokenAt, $reason] = [$expectedSequence, 'A row was altered after it was written.'];
                break;
            }

            $previousHash = $entry['hash'];
            $expectedSequence++;
        }

        $head = DB::table('audit_chain_heads')->where('id', 1)->first();

        if ($brokenAt === null && (int) $head->last_sequence !== $expectedSequence - 1) {
            [$brokenAt, $reason] = [$expectedSequence, 'Rows are missing from the end of the chain.'];
        }

        return [
            'valid' => $brokenAt === null,
            'checked' => $expectedSequence - 1,
            'last_sequence' => (int) $head->last_sequence,
            'last_hash' => $head->last_hash,
            'broken_at' => $brokenAt,
            'reason' => $reason,
        ];
    }

    private function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $entries = $this->buffer;
        $this->buffer = [];

        $head = DB::table('audit_chain_heads')->where('id', 1)->lockForUpdate()->first();
        $sequence = (int) $head->last_sequence;
        $previousHash = $head->last_hash;

        foreach ($entries as $entry) {
            $sequence++;
            $hash = $this->hashFor($entry, $sequence, $previousHash);

            DB::table('audit_logs')->insert([
                ...$entry,
                'sequence' => $sequence,
                'previous_hash' => $previousHash,
                'hash' => $hash,
            ]);

            $previousHash = $hash;
        }

        DB::table('audit_chain_heads')->where('id', 1)->update([
            'last_sequence' => $sequence,
            'last_hash' => $previousHash,
        ]);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function hashFor(array $entry, int $sequence, string $previousHash): string
    {
        $fields = [$sequence, $previousHash];

        foreach (AuditLog::HASHED_COLUMNS as $column) {
            $fields[] = (string) ($entry[$column] ?? '');
        }

        return hash_hmac('sha256', $this->encode(array_map('strval', $fields)), (string) config('fund.audit_hmac_key'));
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function cleanText(string $text, int $limit): string
    {
        return mb_substr(mb_scrub($text, 'UTF-8'), 0, $limit);
    }

    private function currentActor(): ?User
    {
        try {
            $user = Auth::user() ?? Auth::guard('web')->user();

            return $user instanceof User ? $user : null;
        } catch (Throwable) {
            return null;
        }
    }
}
