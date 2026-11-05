<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AccountStatus;
use App\Exceptions\FinancialRuleViolation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMemberRequest;
use App\Http\Requests\Admin\UpdateMemberRequest;
use App\Http\Resources\DuesObligationResource;
use App\Http\Resources\LedgerEntryResource;
use App\Http\Resources\MemberResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DuesService;
use App\Services\InvitationService;
use App\Services\RegistrationService;
use App\Services\ReportService;
use App\Support\BusinessCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    /** Columns a client may sort by. Anything else is refused by validation. */
    private const SORTABLE = ['name', 'joined_on', 'created_at', 'member_code'];

    public function __construct(
        private readonly InvitationService $invitations,
        private readonly DuesService $dues,
        private readonly AuditLogger $audit,
        private readonly RegistrationService $registrations,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(AccountStatus::class)],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
        ]);

        return MemberResource::collection(
            User::query()->members()
                ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('member_code', 'like', "%{$term}%")))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->orderBy($filters['sort'] ?? 'name')
                ->paginate(25),
        );
    }

    public function store(StoreMemberRequest $request): JsonResponse
    {
        $member = $this->invitations->registerMember($request->validated(), $request->user());

        return (new MemberResource($member))->response()->setStatusCode(201);
    }

    public function show(User $member): JsonResponse
    {
        Gate::authorize('view', $member);
        abort_unless($member->isMember(), 404, 'Not found.');

        return response()->json([
            'data' => [
                'member' => new MemberResource($member),
                'dues' => $this->dues->summaryFor($member),
                'months' => DuesObligationResource::collection($this->dues->obligationsFor($member)->sortByDesc(fn ($obligation): string => $obligation->period->format('Y-m-d'))->values()),
            ],
        ]);
    }

    public function update(UpdateMemberRequest $request, User $member): MemberResource
    {
        $data = $request->validated();

        $this->audit->atomic(function () use ($member, $data) {
            $member->fill($data);

            if ($member->isDirty('email')) {
                $member->email_verified_at = null;
            }

            $member->save();
        });

        return new MemberResource($member);
    }

    public function resendInvitation(Request $request, User $member): JsonResponse
    {
        Gate::authorize('manage', $member);

        $this->invitations->resend($member, $request->user());

        return response()->json(['message' => 'Invitation sent.']);
    }

    public function approve(Request $request, User $member): MemberResource
    {
        Gate::authorize('manage', $member);

        /** @var array{joined_on: string, monthly_contribution_paise?: int|null} $terms */
        $terms = $request->validate([
            'joined_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.BusinessCalendar::today()->format('Y-m-d')],
            'monthly_contribution_paise' => ['nullable', 'integer', 'min:1', 'max:'.config('fund.max_amount_paise')],
        ]);

        return new MemberResource($this->registrations->approve($member, $terms));
    }

    public function reject(Request $request, User $member): JsonResponse
    {
        Gate::authorize('manage', $member);

        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:400']]);

        $this->registrations->reject($member, $validated['reason']);

        return response()->json(['message' => 'Registration rejected.']);
    }

    public function suspend(User $member): MemberResource
    {
        Gate::authorize('manage', $member);

        if ($member->status !== AccountStatus::Active) {
            throw FinancialRuleViolation::conflict('Only an active member can be suspended.');
        }

        $this->audit->atomic(function () use ($member) {
            $member->forceFill(['status' => AccountStatus::Suspended, 'suspended_at' => now()])->save();
            DB::table('sessions')->where('user_id', $member->id)->delete();
        });

        return new MemberResource($member);
    }

    public function reactivate(User $member): MemberResource
    {
        Gate::authorize('manage', $member);

        if ($member->status !== AccountStatus::Suspended) {
            throw FinancialRuleViolation::conflict('Only a suspended member can be reactivated.');
        }

        $this->audit->atomic(function () use ($member) {
            $member->forceFill(['status' => AccountStatus::Active, 'suspended_at' => null])->save();
            $this->dues->generateArrears($member);
        });

        return new MemberResource($member);
    }

    public function statement(Request $request, User $member, ReportService $reports): AnonymousResourceCollection
    {
        Gate::authorize('view', $member);

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return LedgerEntryResource::collection($reports->memberLedger($member, $filters));
    }
}
