<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class ExpenseController extends Controller
{
    private const RELATIONS = ['fund', 'account', 'creator', 'documents', 'ledgerTransaction.reversal'];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Expense::class);

        $filters = $request->validate([
            'fund_id' => ['nullable', 'integer'],
            'financial_account_id' => ['nullable', 'integer'],
            'beneficiary' => ['nullable', 'string', 'max:160'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return ExpenseResource::collection(
            Expense::query()
                ->when($filters['fund_id'] ?? null, fn ($query, $fund) => $query->where('fund_id', $fund))
                ->when($filters['financial_account_id'] ?? null, fn ($query, $account) => $query->where('financial_account_id', $account))
                ->when($filters['beneficiary'] ?? null, fn ($query, $name) => $query->where('beneficiary', 'like', "%{$name}%"))
                ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('expense_date', '>=', $from))
                ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('expense_date', '<=', $to))
                ->with(self::RELATIONS)
                ->orderByDesc('expense_date')
                ->orderByDesc('id')
                ->paginate(25),
        );
    }

    public function store(StoreExpenseRequest $request, ExpenseService $expenses): JsonResponse
    {
        $expense = $expenses->record(
            $request->user(),
            $request->safe()->except(['documents', 'idempotency_key']),
            array_values(Arr::wrap($request->file('documents', []))),
            $request->idempotencyKey(),
        );

        return (new ExpenseResource($expense->load(self::RELATIONS)))
            ->response()
            ->setStatusCode($expense->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Expense $expense): ExpenseResource
    {
        Gate::authorize('view', $expense);

        return new ExpenseResource($expense->load(self::RELATIONS));
    }
}
