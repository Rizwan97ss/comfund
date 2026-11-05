<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class CurrentUserResource extends JsonResource
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
            'role' => $this->role->value,
            'status' => $this->status->value,
            'member_code' => $this->member_code,
            'joined_on' => $this->joined_on?->format('Y-m-d'),
            'monthly_contribution_paise' => $this->monthly_contribution_paise,
            'email_verified' => $this->email_verified_at !== null,
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
        ];
    }
}
