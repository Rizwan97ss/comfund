<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'status' => $this->status->value,
            'member_code' => $this->member_code,
            'joined_on' => $this->joined_on?->format('Y-m-d'),
            'monthly_contribution_paise' => $this->monthly_contribution_paise,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'outstanding_paise' => $this->when(
                $this->resource->getAttribute('obligated_paise') !== null,
                fn (): int => (int) $this->resource->getAttribute('obligated_paise') - (int) $this->resource->getAttribute('allocated_paise') - (int) $this->resource->getAttribute('waived_paise'),
            ),
        ];
    }
}
