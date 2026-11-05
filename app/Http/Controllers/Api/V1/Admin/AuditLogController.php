<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['access', 'data', 'finance', 'security', 'system'])],
            'severity' => ['nullable', Rule::in(['info', 'notice', 'warning', 'error', 'critical'])],
            'actor_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return AuditLogResource::collection(
            AuditLog::query()
                ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($inner) => $inner
                    ->where('action', 'like', "%{$term}%")
                    ->orWhere('route', 'like', "%{$term}%")
                    ->orWhere('actor_label', 'like', "%{$term}%")
                    ->orWhere('ip_address', 'like', "%{$term}%")
                    ->orWhere('subject_id', $term)))
                ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
                ->when($filters['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
                ->when($filters['actor_id'] ?? null, fn ($query, $actor) => $query->where('actor_id', $actor))
                ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('occurred_at', '>=', $from))
                ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('occurred_at', '<=', $to))
                ->orderByDesc('sequence')
                ->paginate(50),
        );
    }

    public function verify(AuditLogger $audit): JsonResponse
    {
        return response()->json(['data' => $audit->verifyChain()]);
    }
}
