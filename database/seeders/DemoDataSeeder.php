<?php

namespace Database\Seeders;

use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\User;
use App\Services\AccountService;
use App\Services\DuesService;
use App\Services\ExpenseService;
use App\Services\SubmissionService;
use App\Support\BusinessCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A believable community, created through the real services so the audit log, ledger
 * and balances are all consistent. Log in as admin@example.com or member@example.com,
 * both with the password "password".
 */
class DemoDataSeeder extends Seeder
{
    public function run(SubmissionService $submissions, DuesService $dues, ExpenseService $expenses, AccountService $accounts): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Community Treasurer', 'email' => (string) config('fund.demo_admin_email')]);
        $cash = FinancialAccount::query()->where('name', 'Cash in Hand')->firstOrFail();
        $bank = FinancialAccount::query()->where('name', 'Community Bank Account')->firstOrFail();
        $funds = Fund::query()->get()->keyBy('code');

        $members = collect([
            User::factory()->create(['name' => 'Aisha Rahman', 'email' => 'member@example.com', 'joined_on' => BusinessCalendar::currentMonth()->subMonths(8)->format('Y-m-d')]),
            ...User::factory()->count(5)->sequence(
                ['name' => 'Yusuf Ansari'], ['name' => 'Fatima Sheikh'], ['name' => 'Imran Qureshi'], ['name' => 'Zainab Siddiqui'], ['name' => 'Bilal Khan'],
            )->state(['joined_on' => BusinessCalendar::currentMonth()->subMonths(8)->format('Y-m-d')])->create(),
        ]);

        foreach ($members as $member) {
            $member->forceFill(['member_code' => sprintf('MCF-%05d', $member->id)])->save();
            $dues->generateArrears($member);
        }

        foreach ($members->values() as $index => $member) {
            for ($monthsAgo = 8; $monthsAgo >= $index; $monthsAgo--) {
                $month = BusinessCalendar::currentMonth()->subMonths($monthsAgo);

                $submissions->recordManualEntry($admin, [
                    'member_id' => $member->id,
                    'fund_id' => $funds['monthly_fund']->id,
                    'financial_account_id' => $monthsAgo % 2 === 0 ? $cash->id : $bank->id,
                    'amount_paise' => 10000,
                    'applicable_month' => $month->format('Y-m'),
                    'payment_date' => $month->addDays(4)->min(BusinessCalendar::today())->format('Y-m-d'),
                    'payment_method' => $monthsAgo % 2 === 0 ? 'cash' : 'upi',
                    'transaction_reference' => $monthsAgo % 2 === 0 ? null : strtoupper(Str::random(12)),
                    'attribution_note' => 'Collected at the monthly gathering.',
                ], null, (string) Str::uuid());
            }
        }

        foreach ([['zakat', 2500000], ['sadaqah', 450000], ['fitrah', 180000], ['imdad', 600000], ['other_donations', 220000]] as $i => [$code, $amount]) {
            $submissions->recordManualEntry($admin, [
                'member_id' => $members[$i]->id,
                'fund_id' => $funds[$code]->id,
                'financial_account_id' => $bank->id,
                'amount_paise' => $amount,
                'payment_date' => BusinessCalendar::today()->subDays(30 + $i * 12)->format('Y-m-d'),
                'payment_method' => 'bank_transfer',
                'transaction_reference' => strtoupper(Str::random(12)),
                'attribution_note' => 'Received by bank transfer.',
            ], null, (string) Str::uuid());
        }

        $expenses->record($admin, [
            'fund_id' => $funds['zakat']->id,
            'financial_account_id' => $bank->id,
            'amount_paise' => 800000,
            'beneficiary' => 'Widow support, Ward 7',
            'purpose' => 'The poor (Fuqara)',
            'expense_date' => BusinessCalendar::today()->subDays(10)->format('Y-m-d'),
            'payment_method' => 'bank_transfer',
            'transaction_reference' => strtoupper(Str::random(12)),
            'description' => 'Monthly household support.',
        ], [], (string) Str::uuid());

        $expenses->record($admin, [
            'fund_id' => $funds['imdad']->id,
            'financial_account_id' => $bank->id,
            'amount_paise' => 350000,
            'beneficiary' => 'District Hospital',
            'purpose' => 'Medical assistance',
            'expense_date' => BusinessCalendar::today()->subDays(4)->format('Y-m-d'),
            'payment_method' => 'cheque',
            'transaction_reference' => strtoupper(Str::random(8)),
            'description' => 'Surgery contribution for a member family.',
        ], [], (string) Str::uuid());

        $accounts->transfer($admin, [
            'fund_id' => $funds['monthly_fund']->id,
            'from_account_id' => $cash->id,
            'to_account_id' => $bank->id,
            'amount_paise' => 50000,
            'transfer_date' => BusinessCalendar::today()->subDays(2)->format('Y-m-d'),
            'note' => 'Monthly cash deposit.',
        ], (string) Str::uuid());

        $pendingMember = $members[1];
        $submissions->submit($pendingMember, [
            'fund_id' => $funds['sadaqah']->id,
            'amount_paise' => 75000,
            'payment_date' => BusinessCalendar::today()->subDay()->format('Y-m-d'),
            'payment_method' => 'cash',
            'member_note' => 'Handed to the treasurer after Jumuah.',
        ], null, (string) Str::uuid());

        $this->command?->info('Demo data ready. admin@example.com or member@example.com, password: password');
    }
}
