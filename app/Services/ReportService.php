<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\LedgerEntryKind;
use App\Enums\SubmissionStatus;
use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\FundAccountBalance;
use App\Models\LedgerEntry;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(private readonly DuesService $dues) {}

    /**
     * @return array<string, mixed>
     */
    public function adminDashboard(): array
    {
        $month = BusinessCalendar::currentMonth();
        $totals = $this->totalsByKind(LedgerEntry::query());
        $pending = PaymentSubmission::query()->where('status', SubmissionStatus::Pending);
        $monthlyFund = Fund::monthly();
        $members = User::query()->members();

        $thisMonth = DB::table('dues_obligations')->whereDate('period', $month->format('Y-m-d'));
        $expected = (int) (clone $thisMonth)->sum('amount_paise');
        $settled = (int) DB::table('dues_allocations')
            ->join('dues_obligations', 'dues_obligations.id', '=', 'dues_allocations.dues_obligation_id')
            ->whereDate('dues_obligations.period', $month->format('Y-m-d'))
            ->sum('dues_allocations.amount_paise');
        $waived = (int) DB::table('dues_waivers')
            ->join('dues_obligations', 'dues_obligations.id', '=', 'dues_waivers.dues_obligation_id')
            ->whereDate('dues_obligations.period', $month->format('Y-m-d'))
            ->sum('dues_waivers.amount_paise');

        return [
            'as_of' => BusinessCalendar::today()->format('Y-m-d'),
            'totals' => [
                'collections_paise' => $totals[LedgerEntryKind::Collection->value],
                'expenses_paise' => abs($totals[LedgerEntryKind::Expense->value]),
                'opening_paise' => $totals[LedgerEntryKind::Opening->value],
                'available_paise' => (int) FundAccountBalance::query()->sum('balance_paise'),
                'monthly_dues_paise' => (int) LedgerEntry::query()->where('fund_id', $monthlyFund->id)->where('kind', LedgerEntryKind::Collection)->sum('amount_paise'),
                'donations_paise' => (int) LedgerEntry::query()->where('fund_id', '!=', $monthlyFund->id)->where('kind', LedgerEntryKind::Collection)->sum('amount_paise'),
            ],
            'this_month' => [
                'period' => $month->format('Y-m'),
                'expected_paise' => $expected,
                'collected_paise' => $settled,
                'waived_paise' => $waived,
                'outstanding_paise' => max(0, $expected - $settled - $waived),
            ],
            'pending' => [
                'count' => (clone $pending)->count(),
                'amount_paise' => (int) (clone $pending)->sum('amount_paise'),
            ],
            'members' => [
                'active' => (clone $members)->where('status', AccountStatus::Active)->count(),
                'invited' => (clone $members)->where('status', AccountStatus::Invited)->count(),
                'with_arrears' => $this->membersWithOutstanding()->count(),
            ],
            'funds' => $this->fundBalances(),
            'accounts' => $this->accountBalances(),
            'trend' => $this->monthlyTrend($month->subMonths(11), $month),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function memberDashboard(User $member): array
    {
        $submissions = $member->submissions();
        $byFund = LedgerEntry::query()
            ->where('member_id', $member->id)
            ->where('kind', LedgerEntryKind::Collection)
            ->selectRaw('fund_id, SUM(amount_paise) as total')
            ->groupBy('fund_id')
            ->pluck('total', 'fund_id');

        $funds = Fund::query()->orderBy('sort_order')->get();
        $monthly = $funds->firstWhere('code', Fund::MONTHLY);

        return [
            'as_of' => BusinessCalendar::today()->format('Y-m-d'),
            'total_approved_paise' => (int) $byFund->sum(),
            'voluntary_paise' => (int) $byFund->except([$monthly?->id])->sum(),
            'by_fund' => $funds->map(fn (Fund $fund): array => [
                'id' => $fund->id,
                'code' => $fund->code,
                'name' => $fund->name,
                'total_paise' => (int) $byFund->get($fund->id, 0),
            ])->values()->all(),
            'dues' => $this->dues->summaryFor($member),
            'pending' => [
                'count' => (clone $submissions)->where('status', SubmissionStatus::Pending)->count(),
                'amount_paise' => (int) (clone $submissions)->where('status', SubmissionStatus::Pending)->sum('amount_paise'),
            ],
            'rejected_count' => (clone $submissions)->where('status', SubmissionStatus::Rejected)->count(),
            'current_rate_paise' => $this->dues->rateFor($member, BusinessCalendar::currentMonth())['amount_paise'],
        ];
    }

    /**
     * One member's confirmed money movements, newest first. Pending and rejected
     * submissions never appear, because they are never in the ledger.
     *
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return LengthAwarePaginator<int, LedgerEntry>
     */
    public function memberLedger(User $member, array $filters): LengthAwarePaginator
    {
        return LedgerEntry::query()
            ->where('member_id', $member->id)
            ->where('kind', LedgerEntryKind::Collection)
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('occurred_on', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('occurred_on', '<=', $to))
            ->with(['fund:id,name', 'transaction:id,narration,type'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(25);
    }

    /**
     * Opening balance, movements and closing balance per fund and per account.
     *
     * @return array<string, mixed>
     */
    public function statement(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'funds' => $this->statementRows(Fund::query()->orderBy('sort_order')->get(['id', 'name', 'code', 'is_restricted']), 'fund_id', $from, $to),
            'accounts' => $this->statementRows(FinancialAccount::query()->orderBy('name')->get(['id', 'name', 'type']), 'financial_account_id', $from, $to),
        ];
    }

    /**
     * @return array<int, array{id: int, code: string, name: string, is_restricted: bool, balance_paise: int}>
     */
    public function fundBalances(): array
    {
        $balances = FundAccountBalance::query()->selectRaw('fund_id, SUM(balance_paise) as total')->groupBy('fund_id')->pluck('total', 'fund_id');

        return Fund::query()->orderBy('sort_order')->get()->map(fn (Fund $fund): array => [
            'id' => $fund->id,
            'code' => $fund->code,
            'name' => $fund->name,
            'is_restricted' => $fund->is_restricted,
            'balance_paise' => (int) $balances->get($fund->id, 0),
        ])->values()->all();
    }

    /**
     * @return array<int, array{id: int, name: string, type: string, is_active: bool, balance_paise: int}>
     */
    public function accountBalances(): array
    {
        $balances = FundAccountBalance::query()->selectRaw('financial_account_id, SUM(balance_paise) as total')->groupBy('financial_account_id')->pluck('total', 'financial_account_id');

        return FinancialAccount::query()->orderBy('name')->get()->map(fn (FinancialAccount $account): array => [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type->value,
            'is_active' => $account->is_active,
            'balance_paise' => (int) $balances->get($account->id, 0),
        ])->values()->all();
    }

    /**
     * Members who owe money, largest arrears first.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function unpaidMembers(int $perPage = 25): LengthAwarePaginator
    {
        return $this->membersWithOutstanding()->orderByRaw('(obligated_paise - allocated_paise - waived_paise) DESC')->paginate($perPage);
    }

    /**
     * @return Builder<User>
     */
    public function membersWithOutstanding(): Builder
    {
        $members = User::query()->members()
            ->addSelect('users.*')
            ->selectSub(fn ($query) => $query->from('dues_obligations')->whereColumn('dues_obligations.member_id', 'users.id')->selectRaw('COALESCE(SUM(amount_paise), 0)'), 'obligated_paise')
            ->selectSub(fn ($query) => $query->from('dues_allocations')->join('dues_obligations', 'dues_obligations.id', '=', 'dues_allocations.dues_obligation_id')->whereColumn('dues_obligations.member_id', 'users.id')->selectRaw('COALESCE(SUM(dues_allocations.amount_paise), 0)'), 'allocated_paise')
            ->selectSub(fn ($query) => $query->from('dues_waivers')->join('dues_obligations', 'dues_obligations.id', '=', 'dues_waivers.dues_obligation_id')->whereColumn('dues_obligations.member_id', 'users.id')->selectRaw('COALESCE(SUM(dues_waivers.amount_paise), 0)'), 'waived_paise');

        return User::query()->fromSub($members, 'users')->whereRaw('(obligated_paise - allocated_paise - waived_paise) > 0');
    }

    /**
     * @param  Builder<LedgerEntry>  $query
     * @return array<string, int>
     */
    private function totalsByKind(Builder $query): array
    {
        $rows = $query->selectRaw('kind, SUM(amount_paise) as total')->groupBy('kind')->pluck('total', 'kind');
        $totals = [];

        foreach (LedgerEntryKind::cases() as $kind) {
            $totals[$kind->value] = (int) $rows->get($kind->value, 0);
        }

        return $totals;
    }

    /**
     * @param  Collection<int, covariant Fund|FinancialAccount>  $subjects
     * @param  'fund_id'|'financial_account_id'  $column
     * @return array<int, array<string, mixed>>
     */
    private function statementRows(Collection $subjects, string $column, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $before = LedgerEntry::query()
            ->whereDate('occurred_on', '<', $from->format('Y-m-d'))
            ->selectRaw("{$column} as subject_id, SUM(amount_paise) as total")
            ->groupBy($column)
            ->pluck('total', 'subject_id');

        $during = LedgerEntry::query()
            ->whereDate('occurred_on', '>=', $from->format('Y-m-d'))
            ->whereDate('occurred_on', '<=', $to->format('Y-m-d'))
            ->selectRaw("{$column} as subject_id, kind, SUM(amount_paise) as total")
            ->groupBy($column, 'kind')
            ->get()
            ->groupBy('subject_id');

        return $subjects->map(function (Fund|FinancialAccount $subject) use ($before, $during): array {
            $rows = $during->get($subject->id, collect())->mapWithKeys(fn (LedgerEntry $row): array => [$row->kind->value => (int) $row->getAttribute('total')]);
            $opening = (int) $before->get($subject->id, 0);
            $adjustments = (int) $rows->get(LedgerEntryKind::Opening->value, 0);
            $collections = (int) $rows->get(LedgerEntryKind::Collection->value, 0);
            $expenses = abs((int) $rows->get(LedgerEntryKind::Expense->value, 0));
            $transfers = (int) $rows->get(LedgerEntryKind::Transfer->value, 0);

            return [
                ...$subject->only(['id', 'name']),
                'opening_paise' => $opening,
                'opening_entries_paise' => $adjustments,
                'collections_paise' => $collections,
                'expenses_paise' => $expenses,
                'transfers_net_paise' => $transfers,
                'closing_paise' => $opening + $adjustments + $collections - $expenses + $transfers,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{month: string, collections_paise: int, expenses_paise: int}>
     */
    private function monthlyTrend(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = LedgerEntry::query()
            ->whereDate('occurred_on', '>=', $from->format('Y-m-d'))
            ->whereDate('occurred_on', '<=', $to->endOfMonth()->format('Y-m-d'))
            ->whereIn('kind', [LedgerEntryKind::Collection, LedgerEntryKind::Expense])
            ->selectRaw('occurred_on, kind, SUM(amount_paise) as total')
            ->groupBy('occurred_on', 'kind')
            ->get()
            ->groupBy(fn (LedgerEntry $row): string => $row->occurred_on->format('Y-m'));

        $trend = [];

        for ($month = $from; $month->lessThanOrEqualTo($to); $month = $month->addMonth()) {
            $rows = $totals->get($month->format('Y-m'), collect());

            $trend[] = [
                'month' => $month->format('Y-m'),
                'collections_paise' => (int) $rows->where('kind', LedgerEntryKind::Collection)->sum(fn (LedgerEntry $row): int => (int) $row->getAttribute('total')),
                'expenses_paise' => -(int) $rows->where('kind', LedgerEntryKind::Expense)->sum(fn (LedgerEntry $row): int => (int) $row->getAttribute('total')),
            ];
        }

        return $trend;
    }
}
