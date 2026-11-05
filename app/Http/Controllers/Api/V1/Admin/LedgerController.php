<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\LedgerTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\LedgerTransactionResource;
use App\Models\LedgerTransaction;
use App\Services\SubmissionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LedgerController extends Controller
{
    private const RELATIONS = ['entries.fund:id,name', 'entries.account:id,name', 'poster:id,name', 'member:id,name', 'reversal:id,uuid,reverses_transaction_id', 'reverses:id,uuid'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(LedgerTransactionType::class)],
            'fund_id' => ['nullable', 'integer'],
            'financial_account_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return LedgerTransactionResource::collection(
            LedgerTransaction::query()
                ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
                ->when($filters['fund_id'] ?? null, fn ($query, $fund) => $query->whereHas('entries', fn ($inner) => $inner->where('fund_id', $fund)))
                ->when($filters['financial_account_id'] ?? null, fn ($query, $account) => $query->whereHas('entries', fn ($inner) => $inner->where('financial_account_id', $account)))
                ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('occurred_on', '>=', $from))
                ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('occurred_on', '<=', $to))
                ->with(self::RELATIONS)
                ->latest('id')
                ->paginate(30),
        );
    }

    public function show(LedgerTransaction $transaction): LedgerTransactionResource
    {
        Gate::authorize('view', $transaction);

        return new LedgerTransactionResource($transaction->load(self::RELATIONS));
    }

    public function reverse(Request $request, LedgerTransaction $transaction, SubmissionService $submissions): LedgerTransactionResource
    {
        Gate::authorize('reverse', $transaction);

        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:400']]);

        return new LedgerTransactionResource($submissions->reverseTransaction($transaction, $validated['reason'], $request->user())->load(self::RELATIONS));
    }
}
