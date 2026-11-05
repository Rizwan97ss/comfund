<?php

namespace App\Policies;

use App\Models\PaymentSubmission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentSubmissionPolicy
{
    /**
     * A member asking for someone else's record learns nothing, not even that it exists.
     */
    public function view(User $user, PaymentSubmission $submission): Response
    {
        return $user->isAdmin() || $submission->member_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->isMember() && $user->isActive();
    }

    public function review(User $user, PaymentSubmission $submission): bool
    {
        return $user->isAdmin();
    }
}
