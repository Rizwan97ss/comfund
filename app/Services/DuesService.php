<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\LedgerEntryKind;
use App\Exceptions\FinancialRuleViolation;
use App\Models\DuesAllocation;
use App\Models\DuesObligation;
use App\Models\DuesWaiver;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\MonthlyRate;
use App\Models\User;
use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Monthly dues. What a member owes is always derived: obligations minus confirmed
 * allocations minus waivers. Nothing here is ever edited; every change is a new row.
 */
class DuesService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Generate one month for every eligible member. Safe to run repeatedly.
     *
     * @return int The number of obligations created
     */
    public function generateForPeriod(CarbonImmutable $period): int
    {
        $created = 0;

        User::query()->members()
            ->whereIn('status', [AccountStatus::Invited, AccountStatus::Active])
            ->whereDate('joined_on', '<=', $period->endOfMonth()->format('Y-m-d'))
            ->chunkById(200, function (Collection $members) use ($period, &$created): void {
                foreach ($members as $member) {
                    $created += $this->generateForMember($member, $period, $period);
                }
            });

        return $created;
    }

    /**
     * Generate every missing month from the member's joining month up to the current
     * month, which is how arrears appear for a member registered with a past joining date.
     */
    public function generateArrears(User $member): int
    {
        if ($member->joined_on === null) {
            return 0;
        }

        return $this->generateForMember(
            $member,
            BusinessCalendar::month($member->joined_on->format('Y-m-d')),
            BusinessCalendar::currentMonth(),
        );
    }

    /**
     * What this member owes for a month if it were generated now.
     *
     * @return array{amount_paise: int, source: string}
     */
    public function rateFor(User $member, CarbonImmutable $period): array
    {
        if ($member->monthly_contribution_paise !== null) {
            return ['amount_paise' => $member->monthly_contribution_paise, 'source' => 'member'];
        }

        $rate = MonthlyRate::query()
            ->whereDate('effective_from', '<=', $period->format('Y-m-d'))
            ->orderByDesc('effective_from')
            ->firstOrFail();

        return ['amount_paise' => $rate->amount_paise, 'source' => 'standard'];
    }

    /**
     * Apply the member's unallocated money to their dues: the preferred month first,
     * then the oldest unpaid month. Anything left stays as an advance.
     */
    public function allocateAvailable(User $member, ?CarbonImmutable $preferredPeriod, ?int $ledgerTransactionId, ?int $actorId): void
    {
        $this->audit->atomic(function () use ($member, $preferredPeriod, $ledgerTransactionId, $actorId) {
            $this->lockMember($member);

            $available = $this->advanceBalance($member);

            if ($available <= 0) {
                return;
            }

            $obligations = $this->obligationsFor($member)
                ->sortBy(fn (DuesObligation $obligation): string => ($preferredPeriod !== null && $obligation->period->format('Y-m') === $preferredPeriod->format('Y-m') ? '0' : '1').$obligation->period->format('Y-m-d'));

            foreach ($obligations as $obligation) {
                if ($available <= 0) {
                    break;
                }

                $portion = min($available, $obligation->outstandingPaise());

                if ($portion > 0) {
                    DuesAllocation::query()->create([
                        'dues_obligation_id' => $obligation->id,
                        'ledger_transaction_id' => $ledgerTransactionId,
                        'amount_paise' => $portion,
                        'reason' => $ledgerTransactionId === null ? 'advance_applied' : 'payment',
                        'created_by' => $actorId,
                    ]);
                    $available -= $portion;
                }
            }
        });
    }

    /**
     * After money is withdrawn by a reversal, take back allocations (newest months
     * first) until no more is allocated than was actually received.
     */
    public function rebalanceAfterReversal(User $member, int $reversalTransactionId, ?int $actorId): void
    {
        $this->audit->atomic(function () use ($member, $reversalTransactionId, $actorId) {
            $this->lockMember($member);

            $shortfall = -$this->advanceBalance($member);

            if ($shortfall > 0) {
                foreach ($this->obligationsFor($member)->sortByDesc(fn (DuesObligation $o): string => $o->period->format('Y-m-d')) as $obligation) {
                    if ($shortfall <= 0) {
                        break;
                    }

                    $portion = min($shortfall, $obligation->paidPaise());

                    if ($portion > 0) {
                        DuesAllocation::query()->create([
                            'dues_obligation_id' => $obligation->id,
                            'ledger_transaction_id' => $reversalTransactionId,
                            'amount_paise' => -$portion,
                            'reason' => 'reversal',
                            'created_by' => $actorId,
                        ]);
                        $shortfall -= $portion;
                    }
                }
            }
        });
    }

    public function waive(DuesObligation $obligation, int $amountPaise, string $reason, User $admin): DuesWaiver
    {
        return $this->audit->atomic(function () use ($obligation, $amountPaise, $reason, $admin) {
            $member = $obligation->member;
            $this->lockMember($member);

            $outstanding = DuesObligation::query()->withSettlement()->findOrFail($obligation->id)->outstandingPaise();

            if ($amountPaise > $outstanding) {
                throw new FinancialRuleViolation('A waiver cannot be larger than the amount outstanding for the month.');
            }

            return DuesWaiver::query()->create([
                'dues_obligation_id' => $obligation->id,
                'amount_paise' => $amountPaise,
                'reason' => $reason,
                'granted_by' => $admin->id,
            ]);
        });
    }

    public function reverseWaiver(DuesWaiver $waiver, string $reason, User $admin): DuesWaiver
    {
        return $this->audit->atomic(function () use ($waiver, $reason, $admin) {
            $member = $waiver->obligation->member;
            $this->lockMember($member);

            if ($waiver->reverses_waiver_id !== null) {
                throw FinancialRuleViolation::conflict('A waiver reversal cannot itself be reversed.');
            }

            if ($waiver->reversal()->exists()) {
                throw FinancialRuleViolation::conflict('This waiver has already been reversed.');
            }

            $reversal = DuesWaiver::query()->create([
                'dues_obligation_id' => $waiver->dues_obligation_id,
                'amount_paise' => -$waiver->amount_paise,
                'reason' => $reason,
                'reverses_waiver_id' => $waiver->id,
                'granted_by' => $admin->id,
            ]);

            $this->allocateAvailable($member, null, null, $admin->id);

            return $reversal;
        });
    }

    /**
     * Confirmed Monthly Fund money not yet applied to any month. Negative only
     * transiently, while a reversal is being processed.
     */
    public function advanceBalance(User $member): int
    {
        $received = (int) LedgerEntry::query()
            ->where('member_id', $member->id)
            ->where('fund_id', Fund::monthly()->id)
            ->where('kind', LedgerEntryKind::Collection)
            ->sum('amount_paise');

        $allocated = (int) DuesAllocation::query()
            ->whereHas('obligation', fn ($query) => $query->where('member_id', $member->id))
            ->sum('amount_paise');

        return $received - $allocated;
    }

    /**
     * @return array{obligated_paise: int, paid_paise: int, waived_paise: int, outstanding_paise: int, advance_paise: int, paid_months: int, partial_months: int, unpaid_months: int, waived_months: int}
     */
    public function summaryFor(User $member): array
    {
        $obligations = $this->obligationsFor($member);
        $statuses = $obligations->countBy(fn (DuesObligation $o): string => $o->settlementStatus());

        return [
            'obligated_paise' => (int) $obligations->sum('amount_paise'),
            'paid_paise' => (int) $obligations->sum(fn (DuesObligation $o): int => $o->paidPaise()),
            'waived_paise' => (int) $obligations->sum(fn (DuesObligation $o): int => $o->waivedPaise()),
            'outstanding_paise' => (int) $obligations->sum(fn (DuesObligation $o): int => $o->outstandingPaise()),
            'advance_paise' => max(0, $this->advanceBalance($member)),
            'paid_months' => (int) $statuses->get('paid', 0),
            'partial_months' => (int) $statuses->get('partial', 0),
            'unpaid_months' => (int) $statuses->get('unpaid', 0),
            'waived_months' => (int) $statuses->get('waived', 0),
        ];
    }

    /**
     * @return Collection<int, DuesObligation>
     */
    public function obligationsFor(User $member): Collection
    {
        return DuesObligation::query()
            ->where('member_id', $member->id)
            ->withSettlement()
            ->orderBy('period')
            ->get();
    }

    private function generateForMember(User $member, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->audit->atomic(function () use ($member, $from, $to) {
            $this->lockMember($member);

            $fundId = Fund::monthly()->id;
            $existing = DuesObligation::query()->where('member_id', $member->id)->pluck('period')
                ->map(fn ($period): string => $period->format('Y-m'))->flip();
            $created = 0;

            for ($period = $from; $period->lessThanOrEqualTo($to); $period = $period->addMonth()) {
                if ($existing->has($period->format('Y-m'))) {
                    continue;
                }

                $rate = $this->rateFor($member, $period);

                DuesObligation::query()->create([
                    'member_id' => $member->id,
                    'fund_id' => $fundId,
                    'period' => $period->format('Y-m-d'),
                    'amount_paise' => $rate['amount_paise'],
                    'rate_source' => $rate['source'],
                ]);
                $created++;
            }

            if ($created > 0) {
                $this->allocateAvailable($member, null, null, null);
            }

            return $created;
        });
    }

    private function lockMember(User $member): void
    {
        User::query()->whereKey($member->id)->lockForUpdate()->first();
    }
}
