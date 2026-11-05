<?php

namespace App\Services;

use App\Enums\LedgerEntryKind;
use App\Enums\LedgerTransactionType;
use App\Exceptions\FinancialRuleViolation;
use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\FundAccountBalance;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * The only way money enters, leaves or moves in the books. Postings are append-only;
 * corrections are new reversal transactions.
 */
class LedgerService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<array{fund_id: int, account_id: int, kind: LedgerEntryKind, amount_paise: int}>  $lines
     */
    public function post(
        LedgerTransactionType $type,
        array $lines,
        CarbonInterface $occurredOn,
        string $narration,
        ?User $postedBy,
        ?int $memberId = null,
        ?string $idempotencyKey = null,
        ?int $reversesTransactionId = null,
    ): LedgerTransaction {
        return $this->audit->atomic(function () use ($type, $lines, $occurredOn, $narration, $postedBy, $memberId, $idempotencyKey, $reversesTransactionId) {
            $this->applyToBalances($lines);

            $transaction = LedgerTransaction::query()->create([
                'uuid' => (string) Str::uuid(),
                'type' => $type,
                'idempotency_key' => $idempotencyKey,
                'reverses_transaction_id' => $reversesTransactionId,
                'member_id' => $memberId,
                'occurred_on' => $occurredOn->format('Y-m-d'),
                'narration' => Str::limit($narration, 497),
                'posted_by' => $postedBy?->id,
            ]);

            foreach ($lines as $line) {
                LedgerEntry::query()->create([
                    'ledger_transaction_id' => $transaction->id,
                    'fund_id' => $line['fund_id'],
                    'financial_account_id' => $line['account_id'],
                    'member_id' => $memberId,
                    'kind' => $line['kind'],
                    'amount_paise' => $line['amount_paise'],
                    'occurred_on' => $occurredOn->format('Y-m-d'),
                ]);
            }

            return $transaction;
        });
    }

    /**
     * Post the exact opposite of a transaction. Its effect on dues is undone by the caller.
     */
    public function reverse(LedgerTransaction $original, CarbonInterface $occurredOn, string $reason, User $actor): LedgerTransaction
    {
        return $this->audit->atomic(function () use ($original, $occurredOn, $reason, $actor) {
            $locked = LedgerTransaction::query()->lockForUpdate()->findOrFail($original->id);

            if ($locked->type === LedgerTransactionType::Reversal) {
                throw FinancialRuleViolation::conflict('A reversal cannot itself be reversed. Record a new entry instead.');
            }

            if ($locked->reversal()->exists()) {
                throw FinancialRuleViolation::conflict('This transaction has already been reversed.');
            }

            /** @var list<array{fund_id: int, account_id: int, kind: LedgerEntryKind, amount_paise: int}> $lines */
            $lines = $locked->entries->map(fn (LedgerEntry $entry): array => [
                'fund_id' => $entry->fund_id,
                'account_id' => $entry->financial_account_id,
                'kind' => $entry->kind,
                'amount_paise' => -$entry->amount_paise,
            ])->values()->all();

            return $this->post(
                type: LedgerTransactionType::Reversal,
                lines: $lines,
                occurredOn: $occurredOn,
                narration: 'Reversal: '.$reason,
                postedBy: $actor,
                memberId: $locked->member_id,
                reversesTransactionId: $locked->id,
            );
        });
    }

    /**
     * Compare the running balances with a full recomputation from the ledger.
     *
     * @return array{balanced: bool, ledger_total_paise: int, differences: list<array{fund_id: int, financial_account_id: int, balance_paise: int, ledger_paise: int}>}
     */
    public function reconcileBalances(): array
    {
        $ledger = LedgerEntry::query()
            ->selectRaw('fund_id, financial_account_id, SUM(amount_paise) as total')
            ->groupBy('fund_id', 'financial_account_id')
            ->get()
            ->mapWithKeys(fn (LedgerEntry $row): array => ["{$row->fund_id}:{$row->financial_account_id}" => (int) $row->getAttribute('total')]);

        $differences = [];

        foreach (FundAccountBalance::query()->get() as $balance) {
            $expected = $ledger->get("{$balance->fund_id}:{$balance->financial_account_id}", 0);

            if ($expected !== $balance->balance_paise) {
                $differences[] = [
                    'fund_id' => $balance->fund_id,
                    'financial_account_id' => $balance->financial_account_id,
                    'balance_paise' => $balance->balance_paise,
                    'ledger_paise' => $expected,
                ];
            }
        }

        return [
            'balanced' => $differences === [],
            'ledger_total_paise' => (int) $ledger->sum(),
            'differences' => $differences,
        ];
    }

    /**
     * Give a new account a zero balance row for every fund, so it can always be locked.
     */
    public function ensureBalanceRowsFor(FinancialAccount $account): void
    {
        foreach (Fund::query()->pluck('id') as $fundId) {
            FundAccountBalance::query()->firstOrCreate(
                ['fund_id' => $fundId, 'financial_account_id' => $account->id],
                ['balance_paise' => 0],
            );
        }
    }

    public function balanceOf(int $fundId, int $accountId): int
    {
        return (int) FundAccountBalance::query()
            ->where(['fund_id' => $fundId, 'financial_account_id' => $accountId])
            ->value('balance_paise');
    }

    /**
     * Lock the affected balances in a fixed order, so concurrent postings cannot
     * deadlock or overspend, then apply the movement or refuse it.
     *
     * @param  list<array{fund_id: int, account_id: int, kind: LedgerEntryKind, amount_paise: int}>  $lines
     */
    private function applyToBalances(array $lines): void
    {
        $movements = [];

        foreach ($lines as $line) {
            if ($line['amount_paise'] === 0) {
                throw new FinancialRuleViolation('A ledger entry cannot be for zero.');
            }

            $key = sprintf('%020d:%020d', $line['fund_id'], $line['account_id']);
            $movements[$key] = ($movements[$key] ?? 0) + $line['amount_paise'];
        }

        ksort($movements);

        foreach ($movements as $key => $movement) {
            [$fundId, $accountId] = array_map('intval', explode(':', $key));

            $balance = FundAccountBalance::query()
                ->where(['fund_id' => $fundId, 'financial_account_id' => $accountId])
                ->lockForUpdate()
                ->firstOrFail();

            $newBalance = $balance->balance_paise + $movement;

            if ($newBalance < 0) {
                throw new FinancialRuleViolation(sprintf(
                    'Insufficient balance: %s holds ₹%s of %s. This would overdraw it.',
                    (string) FinancialAccount::query()->whereKey($accountId)->value('name'),
                    number_format($balance->balance_paise / 100, 2),
                    (string) Fund::query()->whereKey($fundId)->value('name'),
                ));
            }

            $balance->balance_paise = $newBalance;
            $balance->save();
        }
    }
}
