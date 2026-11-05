<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\DuesObligationResource;
use App\Http\Resources\LedgerEntryResource;
use App\Services\DuesService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly DuesService $dues,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->memberDashboard($request->user())]);
    }

    public function dues(Request $request): JsonResponse
    {
        $member = $request->user();

        return response()->json([
            'data' => [
                'summary' => $this->dues->summaryFor($member),
                'months' => DuesObligationResource::collection($this->dues->obligationsFor($member)->sortByDesc(fn ($obligation): string => $obligation->period->format('Y-m-d'))->values()),
            ],
        ]);
    }

    public function statement(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return LedgerEntryResource::collection($this->reports->memberLedger($request->user(), $filters));
    }
}
