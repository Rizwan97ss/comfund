<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;

class RegistrationController extends Controller
{
    public function store(RegisterRequest $request, RegistrationService $registrations): JsonResponse
    {
        $registrations->register($request->validated());

        return response()->json(['message' => 'Your registration has been received.'], 201);
    }
}
