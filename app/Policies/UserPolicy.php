<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $member): bool
    {
        return $user->isAdmin() || $user->is($member);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function manage(User $user, User $member): bool
    {
        return $user->isAdmin() && $member->isMember();
    }
}
