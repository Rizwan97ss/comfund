<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MeController extends Controller
{
    public function show(Request $request): CurrentUserResource
    {
        return new CurrentUserResource($request->user());
    }

    public function updateProfile(Request $request): CurrentUserResource
    {
        $user = $request->user();

        $user->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{10,15}$/', Rule::unique('users', 'phone')->ignore($user)],
            'address' => ['nullable', 'string', 'max:500'],
        ]));

        return new CurrentUserResource($user);
    }

    public function sessions(Request $request): JsonResponse
    {
        $current = $request->hasSession() ? $request->session()->getId() : null;

        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $session): array => [
                'id' => hash('sha256', $session->id),
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_active_at' => date(DATE_ATOM, (int) $session->last_activity),
                'is_current' => $session->id === $current,
            ]);

        return response()->json(['data' => $sessions]);
    }

    public function destroySession(Request $request, string $sessionHash): JsonResponse
    {
        $target = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->pluck('id')
            ->first(fn (string $id): bool => hash_equals(hash('sha256', $id), $sessionHash));

        abort_if($target === null, 404, 'Session not found.');

        DB::table('sessions')->where('id', $target)->delete();

        return response()->json(status: 204);
    }
}
