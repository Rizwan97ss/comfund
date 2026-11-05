<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\LedgerService;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function statement(Request $request, ReportService $reports): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return response()->json(['data' => $reports->statement(CarbonImmutable::parse($validated['from']), CarbonImmutable::parse($validated['to']))]);
    }

    public function integrity(LedgerService $ledger): JsonResponse
    {
        return response()->json(['data' => $ledger->reconcileBalances()]);
    }
}
