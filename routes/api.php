<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\Admin\AccountController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\DuesController;
use App\Http\Controllers\Api\V1\Admin\ExpenseController;
use App\Http\Controllers\Api\V1\Admin\FundController;
use App\Http\Controllers\Api\V1\Admin\LedgerController;
use App\Http\Controllers\Api\V1\Admin\MemberController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Admin\SubmissionReviewController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Member\DashboardController as MemberDashboardController;
use App\Http\Controllers\Api\V1\Member\SubmissionController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Http\Controllers\Api\V1\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('invitations/{token}')->middleware('throttle:invitations')->group(function () {
        Route::get('/', [InvitationController::class, 'show']);
        Route::post('verify', [InvitationController::class, 'verify']);
        Route::post('profile', [InvitationController::class, 'profile']);
        Route::post('activate', [InvitationController::class, 'activate']);
    });

    Route::post('registrations', [RegistrationController::class, 'store'])->middleware('throttle:registrations');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('me', [MeController::class, 'show']);

        Route::middleware('verified')->group(function () {
            Route::patch('me/profile', [MeController::class, 'updateProfile']);
            Route::get('me/sessions', [MeController::class, 'sessions']);
            Route::delete('me/sessions/{sessionHash}', [MeController::class, 'destroySession']);
            Route::get('reference', ReferenceController::class);
            Route::post('activity/page-view', [ActivityController::class, 'pageView']);
            Route::get('documents/{document}', [DocumentController::class, 'show'])->middleware('throttle:documents');

            Route::prefix('member')->middleware('role:member')->group(function () {
                Route::get('dashboard', [MemberDashboardController::class, 'dashboard']);
                Route::get('dues', [MemberDashboardController::class, 'dues']);
                Route::get('statement', [MemberDashboardController::class, 'statement']);
                Route::get('submissions', [SubmissionController::class, 'index']);
                Route::post('submissions', [SubmissionController::class, 'store'])->middleware('throttle:financial-writes');
                Route::get('submissions/{submission}', [SubmissionController::class, 'show']);
            });

            Route::prefix('admin')->middleware('role:admin')->group(function () {
                Route::get('dashboard', AdminDashboardController::class);

                Route::get('members', [MemberController::class, 'index']);
                Route::post('members', [MemberController::class, 'store'])->middleware('throttle:financial-writes');
                Route::get('members/{member}', [MemberController::class, 'show']);
                Route::patch('members/{member}', [MemberController::class, 'update']);
                Route::post('members/{member}/invitation', [MemberController::class, 'resendInvitation'])->middleware('throttle:financial-writes');
                Route::post('members/{member}/approve', [MemberController::class, 'approve'])->middleware('throttle:financial-writes');
                Route::post('members/{member}/reject', [MemberController::class, 'reject'])->middleware('throttle:financial-writes');
                Route::post('members/{member}/suspend', [MemberController::class, 'suspend']);
                Route::post('members/{member}/reactivate', [MemberController::class, 'reactivate']);
                Route::get('members/{member}/statement', [MemberController::class, 'statement']);

                Route::get('submissions', [SubmissionReviewController::class, 'index']);
                Route::get('submissions/{submission}', [SubmissionReviewController::class, 'show']);
                Route::post('submissions/{submission}/approve', [SubmissionReviewController::class, 'approve'])->middleware('throttle:financial-writes');
                Route::post('submissions/{submission}/reject', [SubmissionReviewController::class, 'reject'])->middleware('throttle:financial-writes');
                Route::post('manual-entries', [SubmissionReviewController::class, 'storeManual'])->middleware('throttle:financial-writes');

                Route::get('dues/rates', [DuesController::class, 'rates']);
                Route::post('dues/rates', [DuesController::class, 'storeRate']);
                Route::get('dues/unpaid', [DuesController::class, 'unpaid']);
                Route::post('dues/generate', [DuesController::class, 'generate']);
                Route::get('dues/waivers', [DuesController::class, 'waivers']);
                Route::post('dues/obligations/{obligation}/waive', [DuesController::class, 'waive'])->middleware('throttle:financial-writes');
                Route::post('dues/waivers/{waiver}/reverse', [DuesController::class, 'reverseWaiver'])->middleware('throttle:financial-writes');

                Route::get('funds', [FundController::class, 'index']);
                Route::post('funds/{fund}/purposes', [FundController::class, 'storePurpose']);
                Route::patch('fund-purposes/{purpose}', [FundController::class, 'updatePurpose']);

                Route::get('accounts', [AccountController::class, 'index']);
                Route::post('accounts', [AccountController::class, 'store']);
                Route::post('opening-balances', [AccountController::class, 'storeOpeningBalance'])->middleware('throttle:financial-writes');
                Route::get('transfers', [AccountController::class, 'transfers']);
                Route::post('transfers', [AccountController::class, 'storeTransfer'])->middleware('throttle:financial-writes');
                Route::get('reconciliations', [AccountController::class, 'reconciliations']);
                Route::post('reconciliations', [AccountController::class, 'storeReconciliation']);

                Route::get('expenses', [ExpenseController::class, 'index']);
                Route::post('expenses', [ExpenseController::class, 'store'])->middleware('throttle:financial-writes');
                Route::get('expenses/{expense}', [ExpenseController::class, 'show']);

                Route::get('ledger', [LedgerController::class, 'index']);
                Route::get('ledger/{transaction}', [LedgerController::class, 'show']);
                Route::post('ledger/{transaction}/reverse', [LedgerController::class, 'reverse'])->middleware('throttle:financial-writes');

                Route::get('reports/statement', [ReportController::class, 'statement']);
                Route::get('reports/integrity', [ReportController::class, 'integrity']);

                Route::get('audit-logs', [AuditLogController::class, 'index']);
                Route::get('audit-logs/verify', [AuditLogController::class, 'verify'])->middleware('throttle:documents');
            });
        });
    });
});
