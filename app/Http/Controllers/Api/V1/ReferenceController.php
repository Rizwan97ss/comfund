<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Fund;
use App\Models\FundPurpose;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'funds' => Fund::query()->where('is_active', true)->orderBy('sort_order')->with(['purposes' => fn ($query) => $query->where('is_active', true)->orderBy('name')])->get()->map(fn (Fund $fund): array => [
                'id' => $fund->id,
                'code' => $fund->code,
                'name' => $fund->name,
                'description' => $fund->description,
                'is_mandatory' => $fund->is_mandatory,
                'is_restricted' => $fund->is_restricted,
                // Restricted-fund spending rules are an admin concern.
                'purposes' => $request->user()->isAdmin() ? $fund->purposes->map(fn (FundPurpose $purpose): array => ['id' => $purpose->id, 'name' => $purpose->name])->all() : [],
            ]),
            'payment_methods' => array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::cases()),
        ]);
    }
}
