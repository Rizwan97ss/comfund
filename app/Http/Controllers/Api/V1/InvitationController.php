<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MemberInvitation;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function show(string $token): JsonResponse
    {
        $invitation = $this->invitations->findUsable($token);

        return response()->json(['data' => $this->state($invitation)]);
    }

    public function verify(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate(['phone_last_four' => ['required', 'digits:4']]);
        $invitation = $this->invitations->findUsable($token);

        $this->invitations->verify($invitation, $validated['phone_last_four']);

        return response()->json(['data' => $this->state($invitation->refresh()->load('user'), revealProfile: true)]);
    }

    public function profile(Request $request, string $token): JsonResponse
    {
        $invitation = $this->invitations->findUsable($token);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/', Rule::unique('users', 'phone')->ignore($invitation->user_id)],
            'address' => ['required', 'string', 'max:500'],
        ]);

        $this->invitations->completeProfile($invitation, $validated);

        return response()->json(['data' => $this->state($invitation->refresh()->load('user'), revealProfile: true)]);
    }

    public function activate(Request $request, string $token): JsonResponse
    {
        $invitation = $this->invitations->findUsable($token);

        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = $this->invitations->activate($invitation, $validated['password']);

        Auth::guard('web')->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json(['data' => ['activated' => true]]);
    }

    /**
     * Personal details stay hidden until the person has passed verification.
     *
     * @return array<string, mixed>
     */
    private function state(MemberInvitation $invitation, bool $revealProfile = false): array
    {
        $user = $invitation->user;
        $verified = $invitation->verified_at !== null;

        return [
            'first_name' => strtok($user->name, ' ') ?: $user->name,
            'email_hint' => substr($user->email, 0, 2).'***'.strstr($user->email, '@'),
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'step' => match (true) {
                ! $verified => 'verify',
                $invitation->profile_completed_at === null => 'profile',
                default => 'password',
            },
            'profile' => $revealProfile && $verified ? [
                'name' => $user->name,
                'phone' => $user->phone,
                'address' => $user->address,
            ] : null,
        ];
    }
}
