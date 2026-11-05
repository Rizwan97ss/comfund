<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DocumentPolicy
{
    public function view(User $user, Document $document): Response
    {
        if ($user->isAdmin()) {
            return Response::allow();
        }

        $ownsSubmission = $document->submission()->where('member_id', $user->id)->exists();

        return $ownsSubmission ? Response::allow() : Response::denyAsNotFound();
    }
}
