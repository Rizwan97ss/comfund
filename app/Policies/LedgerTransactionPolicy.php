<?php

namespace App\Policies;

use App\Models\LedgerTransaction;
use App\Models\User;

class LedgerTransactionPolicy
{
    public function view(User $user, LedgerTransaction $transaction): bool
    {
        return $user->isAdmin();
    }

    public function reverse(User $user, LedgerTransaction $transaction): bool
    {
        return $user->isAdmin();
    }
}
