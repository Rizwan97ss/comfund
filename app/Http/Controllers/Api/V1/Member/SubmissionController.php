<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\StoreSubmissionRequest;
use App\Http\Resources\SubmissionResource;
use App\Models\PaymentSubmission;
use App\Services\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SubmissionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(SubmissionStatus::class)],
            'fund_id' => ['nullable', 'integer'],
        ]);

        return SubmissionResource::collection(
            PaymentSubmission::query()
                ->where('member_id', $request->user()->id)
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['fund_id'] ?? null, fn ($query, $fund) => $query->where('fund_id', $fund))
                ->with(['fund', 'document', 'reviewer', 'ledgerTransaction.reversal'])
                ->latest('id')
                ->paginate(20),
        );
    }

    public function store(StoreSubmissionRequest $request, SubmissionService $submissions): JsonResponse
    {
        $submission = $submissions->submit(
            $request->user(),
            $request->safe()->except(['receipt', 'idempotency_key']),
            $request->file('receipt'),
            $request->idempotencyKey(),
        );

        return (new SubmissionResource($submission->load(['fund', 'document', 'reviewer', 'ledgerTransaction.reversal'])))
            ->response()
            ->setStatusCode($submission->wasRecentlyCreated ? 201 : 200);
    }

    public function show(PaymentSubmission $submission): SubmissionResource
    {
        Gate::authorize('view', $submission);

        return new SubmissionResource($submission->load(['fund', 'document', 'reviewer', 'ledgerTransaction.reversal']));
    }
}
