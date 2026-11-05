<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fund;
use App\Models\FundPurpose;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FundController extends Controller
{
    public function index(ReportService $reports): JsonResponse
    {
        $purposes = FundPurpose::query()->orderBy('name')->get()->groupBy('fund_id');

        return response()->json([
            'data' => array_map(fn (array $fund): array => [
                ...$fund,
                'purposes' => $purposes->get($fund['id'], collect())->map(fn (FundPurpose $purpose): array => $purpose->only(['id', 'name', 'is_active']))->values(),
            ], $reports->fundBalances()),
        ]);
    }

    public function storePurpose(Request $request, Fund $fund): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('fund_purposes', 'name')->where('fund_id', $fund->id)],
        ]);

        $purpose = FundPurpose::query()->create(['fund_id' => $fund->id, 'name' => $validated['name'], 'is_active' => true]);

        return response()->json(['data' => $purpose->only(['id', 'name', 'is_active'])], 201);
    }

    public function updatePurpose(Request $request, FundPurpose $purpose): JsonResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        $purpose->update($validated);

        return response()->json(['data' => $purpose->only(['id', 'name', 'is_active'])]);
    }
}
