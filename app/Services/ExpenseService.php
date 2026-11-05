<?php

namespace App\Services;

use App\Enums\LedgerEntryKind;
use App\Enums\LedgerTransactionType;
use App\Exceptions\FinancialRuleViolation;
use App\Models\Document;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ExpenseService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LedgerService $ledger,
        private readonly DocumentService $documents,
    ) {}

    /**
     * Record spending from a fund. Refused if the fund's balance in the chosen account
     * is too low, or if a restricted fund is spent on a purpose it does not allow.
     *
     * @param  array<string, mixed>  $data  fund_id, financial_account_id, amount_paise, beneficiary, purpose, expense_date, payment_method, transaction_reference, description
     * @param  array<int, UploadedFile>  $files
     */
    public function record(User $admin, array $data, array $files, string $idempotencyKey): Expense
    {
        if (($existing = $this->replay($idempotencyKey, $admin)) !== null) {
            return $existing;
        }

        $fund = Fund::query()->where('is_active', true)->findOrFail((int) $data['fund_id']);
        $account = FinancialAccount::query()->where('is_active', true)->findOrFail((int) $data['financial_account_id']);
        $this->assertPurposeAllowed($fund, (string) $data['purpose']);

        /** @var array<int, Document> $stored */
        $stored = [];

        try {
            return $this->audit->atomic(function () use ($admin, $data, $files, $idempotencyKey, $fund, $account, &$stored) {
                foreach ($files as $file) {
                    $stored[] = $this->documents->store($file, $admin, 'documents');
                }

                $transaction = $this->ledger->post(
                    type: LedgerTransactionType::Expense,
                    lines: [[
                        'fund_id' => $fund->id,
                        'account_id' => $account->id,
                        'kind' => LedgerEntryKind::Expense,
                        'amount_paise' => -(int) $data['amount_paise'],
                    ]],
                    occurredOn: CarbonImmutable::parse((string) $data['expense_date']),
                    narration: "{$fund->name}: {$data['purpose']} — {$data['beneficiary']}",
                    postedBy: $admin,
                    idempotencyKey: "expense:{$idempotencyKey}",
                );

                $expense = Expense::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'fund_id' => $fund->id,
                    'financial_account_id' => $account->id,
                    'amount_paise' => $data['amount_paise'],
                    'beneficiary' => $data['beneficiary'],
                    'purpose' => $data['purpose'],
                    'expense_date' => $data['expense_date'],
                    'payment_method' => $data['payment_method'],
                    'transaction_reference' => $data['transaction_reference'] ?? null,
                    'description' => $data['description'] ?? null,
                    'created_by' => $admin->id,
                    'ledger_transaction_id' => $transaction->id,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $expense->documents()->attach(array_map(fn (Document $document): int => $document->id, $stored));

                return $expense;
            });
        } catch (UniqueConstraintViolationException $exception) {
            array_map($this->documents->discard(...), $stored);

            return $this->replay($idempotencyKey, $admin) ?? throw $exception;
        } catch (\Throwable $exception) {
            array_map($this->documents->discard(...), $stored);

            throw $exception;
        }
    }

    private function assertPurposeAllowed(Fund $fund, string $purpose): void
    {
        if ($fund->is_restricted && ! $fund->purposes()->where('is_active', true)->where('name', $purpose)->exists()) {
            throw new FinancialRuleViolation("{$fund->name} is a restricted fund and cannot be spent on this purpose.");
        }
    }

    /**
     * @phpstan-impure
     */
    private function replay(string $idempotencyKey, User $admin): ?Expense
    {
        $existing = Expense::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null && $existing->created_by !== $admin->id) {
            throw FinancialRuleViolation::conflict('This request key has already been used.');
        }

        return $existing;
    }
}
