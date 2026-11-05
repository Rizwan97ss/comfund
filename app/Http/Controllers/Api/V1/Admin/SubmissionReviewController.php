<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SubmissionSource;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveSubmissionRequest;
use App\Http\Requests\Admin\RejectSubmissionRequest;
use App\Http\Requests\Admin\StoreManualEntryRequest;
use App\Http\Resources\SubmissionResource;
use App\Models\FinancialAccount;
use App\Models\PaymentSubmission;
use App\Services\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SubmissionReviewController extends Controller
{
    private const RELATIONS = ['fund', 'document', 'member', 'reviewer', 'submitter', 'account', 'ledgerTransaction.reversal'];

    public function __construct(private readonly SubmissionService $submissions) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(SubmissionStatus::class)],
            'source' => ['nullable', Rule::enum(SubmissionSource::class)],
            'fund_id' => ['nullable', 'integer'],
            'member_id' => ['nullable', 'integer'],
        ]);

        return SubmissionResource::collection(
            PaymentSubmission::query()
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
                ->when($filters['fund_id'] ?? null, fn ($query, $fund) => $query->where('fund_id', $fund))
                ->when($filters['member_id'] ?? null, fn ($query, $member) => $query->where('member_id', $member))
                ->with(self::RELATIONS)
                ->orderBy(($filters['status'] ?? null) === SubmissionStatus::Pending->value ? 'id' : 'reviewed_at', ($filters['status'] ?? null) === SubmissionStatus::Pending->value ? 'asc' : 'desc')
                ->orderByDesc('id')
                ->paginate(25),
        );
    }

    public function show(PaymentSubmission $submission): SubmissionResource
    {
        Gate::authorize('review', $submission);

        $submission->load(self::RELATIONS);

        $submission->setAttribute('same_receipt_count', $submission->document === null ? 0 : PaymentSubmission::query()
            ->whereKeyNot($submission->id)
            ->whereHas('document', fn ($query) => $query->where('sha256', $submission->document->sha256))
            ->count());

        return new SubmissionResource($submission);
    }

    public function approve(ApproveSubmissionRequest $request, PaymentSubmission $submission): SubmissionResource
    {
        $account = FinancialAccount::query()->findOrFail($request->integer('financial_account_id'));

        return new SubmissionResource($this->submissions->approve($submission, $request->user(), $account)->load(self::RELATIONS));
    }

    public function reject(RejectSubmissionRequest $request, PaymentSubmission $submission): SubmissionResource
    {
        return new SubmissionResource($this->submissions->reject($submission, $request->user(), (string) $request->validated('reason'))->load(self::RELATIONS));
    }

    public function storeManual(StoreManualEntryRequest $request): JsonResponse
    {
        $entry = $this->submissions->recordManualEntry(
            $request->user(),
            $request->safe()->except(['receipt', 'idempotency_key']),
            $request->file('receipt'),
            $request->idempotencyKey(),
        );

        return (new SubmissionResource($entry->load(self::RELATIONS)))
            ->response()
            ->setStatusCode($entry->wasRecentlyCreated ? 201 : 200);
    }
}
