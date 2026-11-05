<?php

namespace App\Services;

use App\Enums\LedgerEntryKind;
use App\Enums\LedgerTransactionType;
use App\Exceptions\FinancialRuleViolation;
use App\Models\AccountReconciliation;
use App\Models\AccountTransfer;
use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class AccountService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $data  name, type, account_reference
     */
    public function createAccount(array $data): FinancialAccount
    {
        return $this->audit->atomic(function () use ($data) {
            $account = FinancialAccount::query()->create([...$data, 'is_active' => true]);
            $this->ledger->ensureBalanceRowsFor($account);

            return $account;
        });
    }

    /**
     * Bring money already held before this system into the books. Allowed once per
     * fund and account, unless the earlier opening balance was reversed.
     *
     * @param  array<string, mixed>  $data  fund_id, financial_account_id, amount_paise, as_of, note
     */
    public function recordOpeningBalance(User $admin, array $data): LedgerTransaction
    {
        $key = "opening:{$data['fund_id']}:{$data['financial_account_id']}";

        return $this->audit->atomic(function () use ($admin, $data, $key) {
            $generation = LedgerTransaction::query()
                ->where('type', LedgerTransactionType::OpeningBalance)
                ->where('idempotency_key', 'like', $key.'%')
                ->count();

            $active = LedgerEntry::query()
                ->where(['fund_id' => $data['fund_id'], 'financial_account_id' => $data['financial_account_id'], 'kind' => LedgerEntryKind::Opening])
                ->sum('amount_paise');

            if ((int) $active !== 0) {
                throw FinancialRuleViolation::conflict('An opening balance already exists for this fund and account. Reverse it first to correct it.');
            }

            return $this->ledger->post(
                type: LedgerTransactionType::OpeningBalance,
                lines: [[
                    'fund_id' => (int) $data['fund_id'],
                    'account_id' => (int) $data['financial_account_id'],
                    'kind' => LedgerEntryKind::Opening,
                    'amount_paise' => (int) $data['amount_paise'],
                ]],
                occurredOn: CarbonImmutable::parse((string) $data['as_of']),
                narration: 'Opening balance'.(empty($data['note']) ? '' : ': '.$data['note']),
                postedBy: $admin,
                idempotencyKey: $key.':'.$generation,
            );
        });
    }

    /**
     * Move a fund's money between two physical accounts. Fund totals do not change, and
     * money can never move between funds: no such operation exists in the system.
     *
     * @param  array<string, mixed>  $data  fund_id, from_account_id, to_account_id, amount_paise, transfer_date, transaction_reference, note
     */
    public function transfer(User $admin, array $data, string $idempotencyKey): AccountTransfer
    {
        if (($existing = $this->findTransfer($idempotencyKey)) !== null) {
            return $existing;
        }

        $fund = Fund::query()->findOrFail((int) $data['fund_id']);
        $from = FinancialAccount::query()->where('is_active', true)->findOrFail((int) $data['from_account_id']);
        $to = FinancialAccount::query()->where('is_active', true)->findOrFail((int) $data['to_account_id']);

        try {
            return $this->audit->atomic(function () use ($admin, $data, $idempotencyKey, $fund, $from, $to) {
                $transaction = $this->ledger->post(
                    type: LedgerTransactionType::AccountTransfer,
                    lines: [
                        ['fund_id' => $fund->id, 'account_id' => $from->id, 'kind' => LedgerEntryKind::Transfer, 'amount_paise' => -(int) $data['amount_paise']],
                        ['fund_id' => $fund->id, 'account_id' => $to->id, 'kind' => LedgerEntryKind::Transfer, 'amount_paise' => (int) $data['amount_paise']],
                    ],
                    occurredOn: CarbonImmutable::parse((string) $data['transfer_date']),
                    narration: "{$fund->name}: {$from->name} to {$to->name}",
                    postedBy: $admin,
                    idempotencyKey: "transfer:{$idempotencyKey}",
                );

                return AccountTransfer::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'fund_id' => $fund->id,
                    'from_account_id' => $from->id,
                    'to_account_id' => $to->id,
                    'amount_paise' => $data['amount_paise'],
                    'transfer_date' => $data['transfer_date'],
                    'transaction_reference' => $data['transaction_reference'] ?? null,
                    'note' => $data['note'] ?? null,
                    'created_by' => $admin->id,
                    'ledger_transaction_id' => $transaction->id,
                    'idempotency_key' => $idempotencyKey,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            return $this->findTransfer($idempotencyKey) ?? throw $exception;
        }
    }

    /**
     * @phpstan-impure
     */
    private function findTransfer(string $idempotencyKey): ?AccountTransfer
    {
        return AccountTransfer::query()->where('idempotency_key', $idempotencyKey)->first();
    }

    /**
     * @param  array<string, mixed>  $data  financial_account_id, as_of, statement_balance_paise, note
     */
    public function reconcile(User $admin, array $data): AccountReconciliation
    {
        $book = (int) LedgerEntry::query()
            ->where('financial_account_id', $data['financial_account_id'])
            ->whereDate('occurred_on', '<=', $data['as_of'])
            ->sum('amount_paise');

        return AccountReconciliation::query()->create([
            ...$data,
            'book_balance_paise' => $book,
            'created_by' => $admin->id,
        ]);
    }
}
