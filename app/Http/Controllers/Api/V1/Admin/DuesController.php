<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Jobs\GenerateMonthlyDues;
use App\Models\DuesObligation;
use App\Models\DuesWaiver;
use App\Models\MonthlyRate;
use App\Services\DuesService;
use App\Services\ReportService;
use App\Support\BusinessCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DuesController extends Controller
{
    public function __construct(private readonly DuesService $dues) {}

    public function rates(): JsonResponse
    {
        return response()->json([
            'data' => MonthlyRate::query()->with('creator:id,name')->orderByDesc('effective_from')->get()->map(fn (MonthlyRate $rate): array => [
                'id' => $rate->id,
                'amount_paise' => $rate->amount_paise,
                'effective_from' => $rate->effective_from->format('Y-m-d'),
                'note' => $rate->note,
                'created_by' => $rate->creator?->name,
            ]),
        ]);
    }

    /**
     * A new rate only ever applies from now on. Obligations already generated keep
     * the amount they were created with.
     */
    public function storeRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount_paise' => ['required', 'integer', 'min:1', 'max:'.config('fund.max_amount_paise')],
            'effective_month' => ['required', 'date_format:Y-m', 'after_or_equal:'.BusinessCalendar::currentMonth()->format('Y-m')],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $effective = BusinessCalendar::month($validated['effective_month'])->format('Y-m-d');

        if (MonthlyRate::query()->whereDate('effective_from', $effective)->exists()) {
            return response()->json(['message' => 'A rate is already set for that month.', 'errors' => ['effective_month' => ['A rate is already set for that month.']]], 422);
        }

        $rate = MonthlyRate::query()->create([
            'amount_paise' => $validated['amount_paise'],
            'effective_from' => $effective,
            'note' => $validated['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => ['id' => $rate->id]], 201);
    }

    public function unpaid(ReportService $reports): AnonymousResourceCollection
    {
        return MemberResource::collection($reports->unpaidMembers());
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate(['period' => ['required', 'date_format:Y-m', 'before_or_equal:'.BusinessCalendar::currentMonth()->format('Y-m')]]);

        GenerateMonthlyDues::dispatch($validated['period']);

        return response()->json(['message' => 'Dues generation started.'], 202);
    }

    public function waive(Request $request, DuesObligation $obligation): JsonResponse
    {
        $validated = $request->validate([
            'amount_paise' => ['required', 'integer', 'min:1', 'max:'.config('fund.max_amount_paise')],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $waiver = $this->dues->waive($obligation, $validated['amount_paise'], $validated['reason'], $request->user());

        return response()->json(['data' => ['id' => $waiver->id]], 201);
    }

    public function waivers(Request $request): JsonResponse
    {
        $filters = $request->validate(['member_id' => ['nullable', 'integer']]);

        $waivers = DuesWaiver::query()
            ->with(['obligation.member:id,name', 'grantor:id,name', 'reversal:id,reverses_waiver_id'])
            ->when($filters['member_id'] ?? null, fn ($query, $member) => $query->whereHas('obligation', fn ($inner) => $inner->where('member_id', $member)))
            ->latest('id')
            ->paginate(25);

        return response()->json($waivers->through(fn (DuesWaiver $waiver): array => [
            'id' => $waiver->id,
            'member' => $waiver->obligation->member->name,
            'period' => $waiver->obligation->period->format('Y-m'),
            'amount_paise' => $waiver->amount_paise,
            'reason' => $waiver->reason,
            'granted_by' => $waiver->grantor->name,
            'is_reversal' => $waiver->reverses_waiver_id !== null,
            'is_reversed' => $waiver->reversal !== null,
            'created_at' => $waiver->created_at?->toIso8601String(),
        ]));
    }

    public function reverseWaiver(Request $request, DuesWaiver $waiver): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $reversal = $this->dues->reverseWaiver($waiver, $validated['reason'], $request->user());

        return response()->json(['data' => ['id' => $reversal->id]], 201);
    }
}
