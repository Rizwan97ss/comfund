<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): JsonResponse
    {
        return response()->json(['data' => $reports->adminDashboard()]);
    }
}
