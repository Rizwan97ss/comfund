<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Single-page navigation never reaches the server, so the client reports each page view.
     */
    public function pageView(Request $request, AuditLogger $audit): JsonResponse
    {
        $validated = $request->validate(['path' => ['required', 'string', 'max:200', 'regex:/^\/[A-Za-z0-9\-\/_]*$/']]);

        $audit->record('page.viewed', 'access', payload: ['path' => $validated['path']]);

        return response()->json(status: 204);
    }
}
