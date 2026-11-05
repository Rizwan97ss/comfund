<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Concerns\FinancialValidationRules;
use App\Enums\FinancialAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTransferRequest;
use App\Http\Resources\LedgerTransactionResource;
use App\Models\AccountReconciliation;
use App\Models\AccountTransfer;
use App\Services\AccountService;
use App\Services\ReportService;
use App\Support\BusinessCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    use FinancialValidationRules;

    public function __construct(private readonly AccountService $accounts) {}

    public function index(ReportService $reports): JsonResponse
    {
        return response()->json(['data' => $reports->accountBalances()]);
    }

    public function store(Request $request): JsonResponse
    {
        $account = $this->accounts->createAccount($request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:financial_accounts,name'],
            'type' => ['required', Rule::enum(FinancialAccountType::class)],
            'account_reference' => ['nullable', 'string', 'max:40'],
        ]));

        return response()->json(['data' => ['id' => $account->id]], 201);
    }

    public function storeOpeningBalance(Request $request): LedgerTransactionResource
    {
        $transaction = $this->accounts->recordOpeningBalance($request->user(), $request->validate([
            'fund_id' => ['required', 'integer', 'exists:funds,id'],
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'amount_paise' => $this->amountRules(),
            'as_of' => $this->pastOrTodayRules(),
            'note' => ['nullable', 'string', 'max:200'],
        ]));

        return new LedgerTransactionResource($transaction->load('entries'));
    }

    public function transfers(): JsonResponse
    {
        $transfers = AccountTransfer::query()
            ->with(['fund:id,name', 'fromAccount:id,name', 'toAccount:id,name', 'creator:id,name', 'ledgerTransaction.reversal'])
            ->latest('id')
            ->paginate(25);

        return response()->json($transfers->through(fn (AccountTransfer $transfer): array => [
            'uuid' => $transfer->uuid,
            'fund' => $transfer->fund->name,
            'from_account' => $transfer->fromAccount->name,
            'to_account' => $transfer->toAccount->name,
            'amount_paise' => $transfer->amount_paise,
            'transfer_date' => $transfer->transfer_date->format('Y-m-d'),
            'transaction_reference' => $transfer->transaction_reference,
            'note' => $transfer->note,
            'created_by' => $transfer->creator->name,
            'ledger_uuid' => $transfer->ledgerTransaction->uuid,
            'is_reversed' => $transfer->ledgerTransaction->reversal !== null,
        ]));
    }

    public function storeTransfer(StoreTransferRequest $request): JsonResponse
    {
        $transfer = $this->accounts->transfer(
            $request->user(),
            $request->safe()->except('idempotency_key'),
            $request->idempotencyKey(),
        );

        return response()->json(['data' => ['uuid' => $transfer->uuid]], $transfer->wasRecentlyCreated ? 201 : 200);
    }

    public function reconciliations(): JsonResponse
    {
        $reconciliations = AccountReconciliation::query()->with(['account:id,name', 'creator:id,name'])->latest('id')->paginate(25);

        return response()->json($reconciliations->through(fn (AccountReconciliation $reconciliation): array => [
            'id' => $reconciliation->id,
            'account' => $reconciliation->account->name,
            'as_of' => $reconciliation->as_of->format('Y-m-d'),
            'statement_balance_paise' => $reconciliation->statement_balance_paise,
            'book_balance_paise' => $reconciliation->book_balance_paise,
            'difference_paise' => $reconciliation->statement_balance_paise - $reconciliation->book_balance_paise,
            'note' => $reconciliation->note,
            'created_by' => $reconciliation->creator->name,
        ]));
    }

    public function storeReconciliation(Request $request): JsonResponse
    {
        $reconciliation = $this->accounts->reconcile($request->user(), $request->validate([
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'as_of' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.BusinessCalendar::today()->format('Y-m-d')],
            'statement_balance_paise' => ['required', 'integer', 'min:0', 'max:'.config('fund.max_amount_paise')],
            'note' => ['nullable', 'string', 'max:500'],
        ]));

        return response()->json(['data' => [
            'id' => $reconciliation->id,
            'book_balance_paise' => $reconciliation->book_balance_paise,
            'difference_paise' => $reconciliation->statement_balance_paise - $reconciliation->book_balance_paise,
        ]], 201);
    }
}
